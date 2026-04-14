<?php

namespace App\Services\Telephony;

use App\Jobs\CallNoAnswerTimeoutJob;
use App\Models\CallSession;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Services\DispositionService;
use App\Support\CallErrors;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;

class CallOrchestrationService
{
    public function __construct(
        protected VicidialProxyService $vicidialProxy,
        protected CallStateService $callStateService,
        protected DispositionService $dispositionService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Start an outbound call via ViciDial external_dial.
     * ViciDial rings the agent's registered phone, then dials the customer through its own trunk.
     * This replaces the direct AMI originate path which caused single-ping AMI connections and
     * failed to ring the agent when using ViciDial's SIP registration.
     *
     * @return OperationResult with data: ['session_id' => int] on success
     */
    public function startOutboundCall(User $user, string $campaign, string $phoneNumber, ?int $leadId = null, string $phoneCode = '1'): OperationResult
    {
        // Use getActiveSession so stale dialing/ringing sessions are auto-terminated
        // before checking, preventing phantom "already in call" blocks.
        $active = $this->getActiveSession($user);
        if ($active) {
            return OperationResult::failure(
                CallErrors::MESSAGES[CallErrors::ALREADY_IN_CALL],
                CallErrors::toJson(CallErrors::ALREADY_IN_CALL),
            );
        }

        if ($this->dispositionService->hasPendingDisposition($user->id)) {
            return OperationResult::failure(
                CallErrors::MESSAGES[CallErrors::DIAL_BLOCKED_DISPOSITION],
                CallErrors::toJson(CallErrors::DIAL_BLOCKED_DISPOSITION),
            );
        }

        if (empty($user->vici_user) || empty($user->vici_pass)) {
            return OperationResult::failure(
                'ViciDial credentials are not set. Contact administrator.',
                CallErrors::toJson(CallErrors::EXTENSION_OFFLINE),
            );
        }

        $skipAgentSessionCheck = app()->runningInConsole() && ! app()->runningUnitTests();

        if (! $skipAgentSessionCheck && config('vicidial.require_vicidial_agent_session_before_dial', true)) {
            $agentSession = VicidialAgentSession::query()
                ->where('user_id', $user->id)
                ->where('campaign_code', $campaign)
                ->first();

            if (! $agentSession
                || ! in_array($agentSession->session_status, VicidialSessionService::USABLE_STATUSES, true)) {
                return OperationResult::failure(
                    'Log into VICIdial for this campaign on the agent screen first, then wait until Online.',
                    CallErrors::toJson(CallErrors::VICIDIAL_AGENT_NOT_LOGGED_IN),
                );
            }
        }

        // Create session first so we have a session_id for the timeout job
        try {
            $session = DB::transaction(function () use ($user, $campaign, $phoneNumber, $leadId) {
                return CallSession::create([
                    'user_id' => $user->id,
                    'campaign_code' => $campaign,
                    'lead_id' => $leadId,
                    'phone_number' => $phoneNumber,
                    'status' => CallSession::STATUS_DIALING,
                    'dialed_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallOrchestrationService', 'Failed to create CallSession', [
                'error' => $e->getMessage(),
                'campaign' => $campaign,
                'user_id' => $user->id,
            ]);

            return OperationResult::failure($e->getMessage());
        }

        // Dial via ViciDial external_dial API – ViciDial handles ringing the agent and
        // bridging through the configured trunk, avoiding direct AMI originate spam.
        $dialParams = [
            'value' => $phoneNumber,
            'phone_number' => $phoneNumber,
            'phone_code' => $phoneCode,
            'search' => 'YES',
            'preview' => 'NO',
            'focus' => 'YES',
        ];
        if ($leadId) {
            $dialParams['lead_id'] = $leadId;
        }
        $dialResult = $this->vicidialProxy->execute($user, $campaign, 'external_dial', $dialParams);

        if (! $dialResult['success']) {
            $this->telephonyLogger->warning('CallOrchestrationService', 'ViciDial external_dial failed', [
                'user_id' => $user->id,
                'number' => $phoneNumber,
                'response' => $dialResult['raw_response'],
            ]);
            $this->callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => 'vicidial_dial_failed',
            ], true);

            $errorCode = $dialResult['failure_code'] ?? CallErrors::VICIDIAL_DIAL_FAILED;

            return OperationResult::failure(
                $dialResult['message'] ?? 'ViciDial dial request failed.',
                CallErrors::toJson($errorCode),
            );
        }

        // Transition to ringing and schedule no-answer timeout
        try {
            DB::transaction(function () use ($session) {
                $this->callStateService->transition($session, CallSession::STATUS_RINGING);
                CallNoAnswerTimeoutJob::dispatch($session->id)->delay(
                    now()->addSeconds(config('webrtc.no_answer_timeout', 45)),
                );
            });
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallOrchestrationService', 'Failed to transition to ringing', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            $this->callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => 'vicidial_dial_failed',
            ], true);

            return OperationResult::failure(
                'Call setup failed. Please try again.',
                CallErrors::toJson(CallErrors::NETWORK_FAILURE),
            );
        }

        return OperationResult::success(['session_id' => $session->id]);
    }

    /**
     * Record hangup for the agent's active call. Idempotent.
     *
     * ViciDial prescribed sequence: PAUSE → HANGUP → (dispo) → STATUS.
     * Each ViciDial call is wrapped so unreachability never blocks the CRM.
     */
    public function hangup(User $user, ?int $sessionId = null): OperationResult
    {
        $session = $sessionId
            ? CallSession::where('user_id', $user->id)->find($sessionId)
            : CallSession::where('user_id', $user->id)->active()->first();

        if (! $session) {
            return OperationResult::failure('No active call found');
        }

        $campaign = $session->campaign_code;

        // Step 1: Auto-pause the agent so ViciDial stops routing new calls.
        try {
            $this->vicidialProxy->execute($user, $campaign, 'external_pause', [
                'value' => 'PAUSE',
            ]);
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('CallOrchestrationService', 'external_pause failed (non-blocking)', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 2: Tell ViciDial to hang up the customer call leg.
        try {
            $this->vicidialProxy->execute($user, $campaign, 'external_hangup', [
                'value' => '1',
            ]);
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('CallOrchestrationService', 'external_hangup failed (non-blocking)', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 3: Transition CRM call state to wrapup / ended.
        return $this->callStateService->recordHangup($session, ['end_reason' => 'agent_hangup']);
    }

    /**
     * Get the agent's current active call session.
     * Auto-terminates sessions stuck in pre-answer states longer than the
     * no-answer timeout, and any session older than STALE_CALL_MINUTES.
     */
    public function getActiveSession(User $user): ?CallSession
    {
        $session = CallSession::where('user_id', $user->id)->active()->orderByDesc('created_at')->first();
        if (! $session) {
            return null;
        }

        $noAnswerTimeout = (int) config('webrtc.no_answer_timeout', 45);

        // Pre-answer states (dialing/ringing) stale after no-answer timeout
        // (covers normal timeouts and orphaned sessions from AMI/PAMI errors)
        if (in_array($session->status, [CallSession::STATUS_DIALING, CallSession::STATUS_RINGING], true)) {
            $staleAt = ($session->dialed_at ?? $session->created_at)->addSeconds($noAnswerTimeout);
            if (now()->greaterThanOrEqualTo($staleAt)) {
                $this->callStateService->transition($session, CallSession::STATUS_FAILED, [
                    'end_reason' => 'stale_session',
                ], true);

                return null;
            }
        }

        // Any session older than the global stale threshold is abandoned
        $globalStaleAt = $session->created_at->addMinutes(CallStateService::STALE_CALL_MINUTES);
        if (now()->greaterThanOrEqualTo($globalStaleAt)) {
            $this->callStateService->transition($session, CallSession::STATUS_ABANDONED, [
                'end_reason' => 'stale_session',
            ], true);

            return null;
        }

        return $session;
    }

    /**
     * Force-complete all active call sessions for a user (e.g. on logout).
     */
    public function forceCompleteAllForUser(User $user): void
    {
        $sessions = CallSession::where('user_id', $user->id)->active()->get();
        foreach ($sessions as $session) {
            $this->callStateService->transition($session, CallSession::STATUS_ABANDONED, [
                'end_reason' => 'user_logout',
            ], true);
        }
    }

    /**
     * Get the agent's pending disposition session (call ended, no disposition yet).
     */
    public function getPendingDispositionSession(User $user): ?CallSession
    {
        return $this->dispositionService->getPendingDispositionSession($user->id);
    }
}
