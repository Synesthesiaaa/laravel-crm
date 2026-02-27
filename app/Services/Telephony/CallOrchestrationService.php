<?php

namespace App\Services\Telephony;

use App\Jobs\CallNoAnswerTimeoutJob;
use App\Models\CallSession;
use App\Models\User;
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
        protected AsteriskAmiService $amiService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Start an outbound call via WebRTC (SIP).
     * AMI Originates to agent's SIP extension; SIP.js auto-answers; Asterisk bridges to GoIP trunk.
     * Blocks if agent has a call requiring disposition.
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
                CallErrors::toJson(CallErrors::ALREADY_IN_CALL)
            );
        }

        if ($this->dispositionService->hasPendingDisposition($user->id)) {
            return OperationResult::failure(
                CallErrors::MESSAGES[CallErrors::DIAL_BLOCKED_DISPOSITION],
                CallErrors::toJson(CallErrors::DIAL_BLOCKED_DISPOSITION)
            );
        }

        if (empty($user->extension)) {
            return OperationResult::failure(
                'No SIP extension assigned. Contact administrator.',
                CallErrors::toJson(CallErrors::EXTENSION_OFFLINE)
            );
        }

        // Create session first so we have a session_id for the timeout job
        try {
            $session = DB::transaction(function () use ($user, $campaign, $phoneNumber, $leadId) {
                return CallSession::create([
                    'user_id'       => $user->id,
                    'campaign_code' => $campaign,
                    'lead_id'       => $leadId,
                    'phone_number'  => $phoneNumber,
                    'status'        => CallSession::STATUS_DIALING,
                    'dialed_at'     => now(),
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

        // Originate via AMI: SIP/{extension} -> SIP/goip-trunk/{number}
        try {
            $amiResult = $this->amiService->originateWebRtc(
                $user->extension,
                $phoneNumber,
                $user->full_name ?: $user->username,
                config('webrtc.no_answer_timeout', 45)
            );
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallOrchestrationService', 'AMI exception during originate', [
                'error' => $e->getMessage(),
                'extension' => $user->extension,
                'number' => $phoneNumber,
            ]);
            $this->callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => 'ami_originate_failed',
            ], true);

            return OperationResult::failure(
                'Call setup failed. Please try again.',
                CallErrors::toJson(CallErrors::CHANNEL_UNAVAILABLE)
            );
        }

        if (! $amiResult['success']) {
            $this->callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => 'ami_originate_failed',
            ], true);

            return OperationResult::failure(
                $amiResult['message'] ?? 'AMI originate failed',
                CallErrors::toJson(CallErrors::CHANNEL_UNAVAILABLE)
            );
        }

        // Transition to ringing and schedule no-answer timeout
        try {
            DB::transaction(function () use ($session) {
                $this->callStateService->transition($session, CallSession::STATUS_RINGING);
                CallNoAnswerTimeoutJob::dispatch($session->id)->delay(
                    now()->addSeconds(config('webrtc.no_answer_timeout', 45))
                );
            });
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallOrchestrationService', 'Failed to transition to ringing', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            $this->callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => 'ami_originate_failed',
            ], true);

            return OperationResult::failure(
                'Call setup failed. Please try again.',
                CallErrors::toJson(CallErrors::CHANNEL_UNAVAILABLE)
            );
        }

        return OperationResult::success(['session_id' => $session->id]);
    }

    /**
     * Record hangup for the agent's active call. Idempotent.
     */
    public function hangup(User $user, ?int $sessionId = null): OperationResult
    {
        $session = $sessionId
            ? CallSession::where('user_id', $user->id)->find($sessionId)
            : CallSession::where('user_id', $user->id)->active()->first();

        if (! $session) {
            return OperationResult::failure('No active call found');
        }

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
