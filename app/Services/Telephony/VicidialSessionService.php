<?php

namespace App\Services\Telephony;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Support\OperationResult;

class VicidialSessionService
{
    /**
     * Session statuses that mean the agent can actually use telephony.
     * Anything outside this list must not enable dial/pause actions.
     */
    public const USABLE_STATUSES = ['ready', 'paused', 'in_call'];

    /**
     * Number of attempts to verify the agent appeared in vicidial_live_agents
     * after the iframe login, and sleep between them (microseconds).
     */
    private const VERIFY_ATTEMPTS   = 3;
    private const VERIFY_SLEEP_US   = 800_000; // 0.8 s per attempt

    public function __construct(
        protected VicidialProxyService $agentApi,
        protected VicidialNonAgentApiService $nonAgentApi,
        protected AttendanceRepositoryInterface $attendanceRepository
    ) {}

    public function loginAgent(
        User $user,
        string $campaign,
        ?string $phoneLogin = null,
        ?string $phonePass = null,
        bool $blended = true,
        array $ingroups = []
    ): OperationResult {
        // Validate VICIdial credentials are present.
        if (empty($user->vici_user) || empty($user->vici_pass)) {
            return OperationResult::failure(
                'ViciDial credentials are not set for your account. Contact your administrator.'
            );
        }

        // Resolve effective phone login (must come from session-panel form or user.extension).
        // Never fall back to vici_user – it is not a phones.login value.
        $effectivePhoneLogin = $phoneLogin ?: (string) ($user->extension ?? '');
        $effectivePhonePass  = $phonePass  ?: (string) ($user->sip_password ?? '');

        if ($effectivePhoneLogin === '') {
            return OperationResult::failure(
                'Phone Login is required to establish a VICIdial session. Enter your extension in the Phone Login field.'
            );
        }

        // Mark session as pending while the iframe login runs.
        $session = $this->getOrCreateSession($user, $campaign);
        $session->fill([
            'phone_login'     => $effectivePhoneLogin,
            'session_status'  => 'login_pending',
            'blended'         => $blended,
            'ingroup_choices' => $this->normalizeIngroups($ingroups),
            'logged_in_at'    => now(),
            'last_synced_at'  => now(),
        ])->save();

        // Attempt Agent API login to pre-register the agent in VICIdial's routing tables.
        // The Agent API login is a lightweight signal; the true session is established
        // via the browser iframe pointing at vicidial.php.
        $loginResult = $this->agentApi->execute($user, $campaign, 'login', [
            'value' => $campaign,
            'query' => array_filter([
                'phone_login' => $effectivePhoneLogin,
                'phone_pass'  => $effectivePhonePass,
                'campaign'    => $campaign,
                'blended'     => $blended ? 'Y' : 'N',
            ], static fn ($v) => $v !== ''),
        ]);

        // Hard-fail on credential rejection – do not proceed.
        if (! $loginResult['success']) {
            $raw = strtolower((string) ($loginResult['raw_response'] ?? ''));
            $msg = strtolower((string) ($loginResult['message'] ?? ''));
            $isCredentialError = str_contains($raw, 'invalid username')
                || str_contains($raw, 'bad login')
                || str_contains($msg, 'credential')
                || str_contains($msg, 'rejected');

            if ($isCredentialError) {
                $session->update(['session_status' => 'login_failed', 'last_synced_at' => now()]);
                return OperationResult::failure(
                    'VICIdial credentials were rejected. Please verify your username and password.'
                );
            }
            // Network/server errors – mark pending so iframe can still recover.
        }

        // Build canonical auto-login URL for the frontend iframe.
        $iframeUrl = $this->buildIframeUrl($user, $campaign, $effectivePhoneLogin, $effectivePhonePass);

        return OperationResult::success([
            'session'        => $session->fresh(),
            'iframe_url'     => $iframeUrl,
            'login_state'    => 'login_pending',
            'vici_login_raw' => $loginResult['raw_response'] ?? null,
        ], 'VICIdial login initiated. Awaiting session confirmation.');
    }

    /**
     * Called by the frontend after the iframe loads to confirm the session is live.
     * Performs bounded retries against agent_status; promotes session to `ready` on success.
     */
    public function verifyLogin(User $user, string $campaign): OperationResult
    {
        $session = $this->getOrCreateSession($user, $campaign);

        for ($i = 0; $i < self::VERIFY_ATTEMPTS; $i++) {
            if ($i > 0) {
                usleep(self::VERIFY_SLEEP_US);
            }

            $status = $this->checkAgentStatusRaw($user, $campaign);
            if ($status['logged_in']) {
                $session->update([
                    'session_status'      => 'ready',
                    'last_status_payload' => $status['payload'],
                    'last_synced_at'      => now(),
                ]);
                return OperationResult::success([
                    'session'     => $session->fresh(),
                    'login_state' => 'ready',
                ], 'Agent session is live and ready.');
            }
        }

        // Still not live – keep pending, frontend will keep polling on its own schedule.
        $session->update(['session_status' => 'login_pending', 'last_synced_at' => now()]);
        return OperationResult::failure(
            'VICIdial session not confirmed yet. Ensure your credentials are correct and try again. ' .
            'The hidden VICIdial session may still be loading.'
        );
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
            'pause_code'     => $code,
            'session_status' => 'paused',
            'last_synced_at' => now(),
        ]);

        try {
            $this->attendanceRepository->log(
                $user->id,
                'pause',
                request()?->ip(),
                strtoupper($code)
            );
        } catch (\Throwable) {
            // Non-blocking: attendance log must not break telephony flow
        }

        return OperationResult::success(['raw_response' => $response['raw_response']]);
    }

    public function logoutAgent(User $user, string $campaign): OperationResult
    {
        $response = $this->agentApi->execute($user, $campaign, 'logout', ['value' => 'LOGOUT']);

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'session_status' => 'logged_out',
            'pause_code'     => null,
            'last_synced_at' => now(),
        ]);

        if (! $response['success']) {
            return OperationResult::success([
                'raw_response' => $response['raw_response'],
                'message'      => $response['message'],
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
                'blended'         => $blended ? 'YES' : 'NO',
                'ingroup_choices' => $choices,
            ],
        ]);

        if (! $response['success']) {
            return OperationResult::failure($response['message'] ?: 'Unable to change in-groups.');
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'ingroup_choices' => $choices,
            'blended'         => $blended,
            'last_synced_at'  => now(),
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
            'count'        => $count,
            'raw_response' => $response['raw_response'],
        ]);
    }

    public function getAgentInGroupInfo(User $user, string $campaign): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'agent_ingroup_info', [
            'agent_user' => (string) $user->vici_user,
            'stage'      => 'text',
        ], true);
    }

    public function getAgentStatus(User $user, string $campaign): OperationResult
    {
        $result = $this->nonAgentApi->execute($user, $campaign, 'agent_status', [
            'agent_user' => (string) $user->vici_user,
            'stage'      => 'pipe',
            'header'     => 'YES',
            'include_ip' => 'YES',
        ], true);

        if (! $result->success) {
            return $result;
        }

        $session = $this->getOrCreateSession($user, $campaign);
        $session->update([
            'last_status_payload' => $result->data,
            'last_synced_at'      => now(),
        ]);

        return $result;
    }

    public function getLocalSession(User $user, string $campaign): VicidialAgentSession
    {
        return $this->getOrCreateSession($user, $campaign);
    }

    /**
     * Build a canonical VICIdial vicidial.php auto-login URL.
     * Uses long-form param names so they are not accidentally filtered by mod_security rules.
     */
    public function buildIframeUrl(
        User $user,
        string $campaign,
        string $phoneLogin,
        string $phonePass
    ): ?string {
        // Resolve the vicidial.php base URL from the active server record.
        $server = $this->nonAgentApi->getServerForCampaign($campaign);
        if (! $server) {
            return null;
        }

        $agentApiUrl = rtrim((string) $server->api_url, '?& ');
        if ($agentApiUrl === '') {
            return null;
        }

        // Derive vicidial.php from api_url by swapping agc/api.php -> agc/vicidial.php.
        if (str_contains($agentApiUrl, 'agc/api.php')) {
            $vicidialPhpUrl = preg_replace('#agc/api\.php.*$#', 'agc/vicidial.php', $agentApiUrl) ?: '';
        } elseif (str_contains($agentApiUrl, 'agc/')) {
            $vicidialPhpUrl = preg_replace('#agc/[^/]+$#', 'agc/vicidial.php', $agentApiUrl) ?: '';
        } else {
            $vicidialPhpUrl = rtrim($agentApiUrl, '/') . '/agc/vicidial.php';
        }

        if ($vicidialPhpUrl === '') {
            return null;
        }

        // Use canonical long-form params: phone_login, phone_pass, VD_login, VD_pass, VD_campaign.
        // `relogin=YES` is required on VICIdial >= 2.14b0.5 to trigger the login sequence.
        return $vicidialPhpUrl . '?' . http_build_query([
            'phone_login' => $phoneLogin,
            'phone_pass'  => $phonePass !== '' ? $phonePass : $phoneLogin, // VD default: pass=login
            'VD_login'    => $user->vici_user,
            'VD_pass'     => $user->vici_pass,
            'VD_campaign' => $campaign,
            'relogin'     => 'YES',
        ]);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * @return array{logged_in: bool, payload: array}
     */
    private function checkAgentStatusRaw(User $user, string $campaign): array
    {
        $result = $this->nonAgentApi->execute($user, $campaign, 'agent_status', [
            'agent_user' => (string) $user->vici_user,
            'stage'      => 'pipe',
            'header'     => 'YES',
            'include_ip' => 'YES',
        ], true);

        $payload = (array) ($result->data ?? []);
        $raw     = strtolower((string) ($payload['raw_response'] ?? ''));

        // agent_status returns error when agent is not in vicidial_live_agents.
        $loggedIn = $result->success && ! str_contains($raw, 'agent not logged in');

        return ['logged_in' => $loggedIn, 'payload' => $payload];
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
