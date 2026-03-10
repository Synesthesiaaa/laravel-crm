<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Support\OperationResult;

class VicidialSessionService
{
    public function __construct(
        protected VicidialProxyService $agentApi,
        protected VicidialNonAgentApiService $nonAgentApi
    ) {}

    public function loginAgent(
        User $user,
        string $campaign,
        ?string $phoneLogin = null,
        ?string $phonePass = null,
        bool $blended = true,
        array $ingroups = []
    ): OperationResult {
        // Validate ViciDial credentials are set before attempting API login.
        if (empty($user->vici_user) || empty($user->vici_pass)) {
            return OperationResult::failure(
                'ViciDial credentials are not set for your account. Contact your administrator.'
            );
        }

        // Attempt actual Agent API login to register agent in vicidial_live_agents.
        // This prevents the CRM showing "logged in" while ViciDial does not know about the agent.
        $loginResult = $this->agentApi->execute($user, $campaign, 'login', [
            'value' => $campaign,
            'query' => array_filter([
                'phone_login' => $phoneLogin ?? (string) ($user->extension ?? ''),
                'phone_pass'  => $phonePass ?? '',
                'campaign'    => $campaign,
                'blended'     => $blended ? 'Y' : 'N',
            ], static fn ($v) => $v !== ''),
        ]);

        // Hard-fail on credential rejection so agent cannot use wrong credentials.
        if (! $loginResult['success']) {
            $msg = strtolower((string) ($loginResult['message'] ?? ''));
            if (str_contains($msg, 'invalid username') || str_contains($msg, 'bad') || str_contains($msg, 'credential')) {
                return OperationResult::failure(
                    'ViciDial credentials were rejected. Please verify your ViciDial username and password.'
                );
            }
            // Non-credential errors (network, server down) fall through to partial login
            // so agents can still use CRM features that do not require ViciDial session.
            $warningMessage = 'ViciDial login could not be confirmed (' . ($loginResult['message'] ?? 'no response') . '). Some features may be unavailable.';
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->fill([
            'phone_login'    => $phoneLogin ?: ($session->phone_login ?: (string) ($user->extension ?? '')),
            'session_status' => isset($warningMessage) ? 'ready_partial' : 'ready',
            'blended'        => $blended,
            'ingroup_choices' => $this->normalizeIngroups($ingroups),
            'logged_in_at'   => now(),
            'last_synced_at' => now(),
        ])->save();

        // Best-effort status sync from ViciDial to reconcile real agent state.
        $status = $this->getAgentStatus($user, $campaign);
        if ($status->success) {
            $payload = (array) ($status->data ?? []);
            $session->update([
                'last_status_payload' => $payload,
                'last_synced_at'      => now(),
            ]);
        }

        return OperationResult::success([
            'session' => $session->fresh(),
            'status'  => $status->data ?? null,
            'vici_login_raw' => $loginResult['raw_response'] ?? null,
        ], $warningMessage ?? 'Agent session initialized.');
    }

    public function pauseAgent(User $user, string $campaign, string $value): OperationResult
    {
        $value = strtoupper(trim($value));
        if (!in_array($value, ['PAUSE', 'RESUME'], true)) {
            return OperationResult::failure('Invalid pause action.');
        }

        $response = $this->agentApi->execute($user, $campaign, 'external_pause', ['value' => $value]);
        if (! $response['success']) {
            return OperationResult::failure($response['message'] ?: 'Pause request failed.');
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'session_status' => $value === 'PAUSE' ? 'paused' : 'ready',
            'last_synced_at' => now(),
        ]);

        return OperationResult::success(['raw_response' => $response['raw_response']]);
    }

    public function setPauseCode(User $user, string $campaign, string $code): OperationResult
    {
        $code = trim($code);
        if ($code === '') {
            return OperationResult::failure('Pause code is required.');
        }

        $response = $this->agentApi->execute($user, $campaign, 'pause_code', ['value' => $code]);
        if (! $response['success']) {
            $message = strtolower((string) ($response['message'] ?? ''));

            // VICIdial requires the agent to be paused before setting pause_code.
            // Auto-pause once, then retry pause_code for better UX.
            if (str_contains($message, 'not paused')) {
                $pauseResult = $this->pauseAgent($user, $campaign, 'PAUSE');
                if (! $pauseResult->success) {
                    return OperationResult::failure($pauseResult->message ?: 'Unable to pause agent before setting pause code.');
                }

                $response = $this->agentApi->execute($user, $campaign, 'pause_code', ['value' => $code]);
            }
        }

        if (! $response['success']) {
            return OperationResult::failure($response['message'] ?: 'Unable to set pause code. Please ensure the agent is logged in and paused.');
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'pause_code' => $code,
            'session_status' => 'paused',
            'last_synced_at' => now(),
        ]);

        return OperationResult::success(['raw_response' => $response['raw_response']]);
    }

    public function logoutAgent(User $user, string $campaign): OperationResult
    {
        $response = $this->agentApi->execute($user, $campaign, 'logout', ['value' => 'LOGOUT']);

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'session_status' => 'logged_out',
            'pause_code' => null,
            'last_synced_at' => now(),
        ]);

        if (! $response['success']) {
            // Still consider local logout successful to prevent UI lock-in.
            return OperationResult::success([
                'raw_response' => $response['raw_response'],
                'message' => $response['message'],
            ], 'Local session logged out, VICIdial reported a warning.');
        }

        return OperationResult::success(['raw_response' => $response['raw_response']]);
    }

    public function changeIngroups(
        User $user,
        string $campaign,
        string $action,
        array $ingroups,
        bool $blended = true
    ): OperationResult {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['CHANGE', 'ADD', 'REMOVE'], true)) {
            return OperationResult::failure('Invalid in-group action.');
        }

        $choices = $this->normalizeIngroups($ingroups);

        $response = $this->agentApi->execute($user, $campaign, 'change_ingroups', [
            'value' => $action,
            'query' => [
                'blended' => $blended ? 'YES' : 'NO',
                'ingroup_choices' => $choices,
            ],
        ]);

        if (! $response['success']) {
            return OperationResult::failure($response['message'] ?: 'Unable to change in-groups.');
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'ingroup_choices' => $choices,
            'blended' => $blended,
            'last_synced_at' => now(),
        ]);

        return OperationResult::success(['raw_response' => $response['raw_response']]);
    }

    public function getCallsInQueue(User $user, string $campaign): OperationResult
    {
        $response = $this->agentApi->execute($user, $campaign, 'calls_in_queue_count', [
            'value' => 'DISPLAY',
        ]);

        if (! $response['success']) {
            return OperationResult::failure($response['message'] ?: 'Unable to fetch queue count.');
        }

        preg_match('/([0-9]+)/', (string) $response['raw_response'], $m);
        $count = isset($m[1]) ? (int) $m[1] : 0;

        return OperationResult::success([
            'count' => $count,
            'raw_response' => $response['raw_response'],
        ]);
    }

    public function getAgentInGroupInfo(User $user, string $campaign): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'agent_ingroup_info', [
            'agent_user' => (string) $user->vici_user,
            'stage' => 'text',
        ], true);
    }

    public function getAgentStatus(User $user, string $campaign): OperationResult
    {
        $result = $this->nonAgentApi->execute($user, $campaign, 'agent_status', [
            'agent_user' => (string) $user->vici_user,
            'stage' => 'pipe',
            'header' => 'YES',
            'include_ip' => 'YES',
        ], true);

        if (! $result->success) {
            return $result;
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'last_status_payload' => $result->data,
            'last_synced_at' => now(),
        ]);

        return $result;
    }

    public function getLocalSession(User $user, string $campaign): VicidialAgentSession
    {
        return $this->getOrCreateSession($user, $campaign);
    }

    protected function getOrCreateSession(User $user, string $campaign): VicidialAgentSession
    {
        return VicidialAgentSession::firstOrCreate(
            ['user_id' => $user->id, 'campaign_code' => $campaign],
            ['session_status' => 'logged_out']
        );
    }

    protected function normalizeIngroups(array $ingroups): string
    {
        $normalized = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $ingroups)));
        return implode(' ', $normalized);
    }
}
