<?php

namespace App\Services\Telephony;

use App\Events\CallStateChanged;
use App\Models\CallSession;
use App\Models\LeadHopper;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;

class CallStateService
{
    public function __construct(
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Valid state transitions: from => [to, ...]
     * All active states allow direct transition to terminal states so that
     * agent hangup and system cleanup can always terminate a session cleanly.
     */
    protected const VALID_TRANSITIONS = [
        CallSession::STATUS_DIALING => [
            CallSession::STATUS_RINGING,
            CallSession::STATUS_ANSWERED,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
            CallSession::STATUS_COMPLETED,
        ],
        CallSession::STATUS_RINGING => [
            CallSession::STATUS_ANSWERED,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
            CallSession::STATUS_COMPLETED,
        ],
        CallSession::STATUS_ANSWERED => [
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_COMPLETED,
        ],
        CallSession::STATUS_IN_CALL => [
            CallSession::STATUS_ON_HOLD,
            CallSession::STATUS_TRANSFERRING,
            CallSession::STATUS_COMPLETED,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
        ],
        CallSession::STATUS_ON_HOLD => [
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_COMPLETED,
            CallSession::STATUS_ABANDONED,
        ],
        CallSession::STATUS_TRANSFERRING => [
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_COMPLETED,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
        ],
    ];

    /**
     * Transitions that bypass validation (force correction, reconciliation).
     * All terminal states are allowed via force to handle edge cases.
     */
    protected const FORCE_CORRECTION_ALLOWED = [
        CallSession::STATUS_COMPLETED,
        CallSession::STATUS_FAILED,
        CallSession::STATUS_ABANDONED,
    ];

    /**
     * Max age (minutes) before reconciliation forces terminal state.
     */
    public const STALE_CALL_MINUTES = 120;

    /**
     * Transition a call session to a new state. Idempotent when to-state matches current.
     *
     * @param  array{end_reason?: string, linkedid?: string, channel?: string, metadata?: array}  $context
     */
    public function transition(CallSession $session, string $toStatus, array $context = [], bool $force = false): OperationResult
    {
        $fromStatus = $session->status;

        if ($fromStatus === $toStatus) {
            return OperationResult::success(null, 'Already in state');
        }

        if ($session->isTerminal()) {
            $this->telephonyLogger->info('CallStateService', 'Ignoring transition from terminal state', [
                'session_id' => $session->id,
                'from' => $fromStatus,
                'to' => $toStatus,
            ]);

            return OperationResult::success(null, 'Already terminal');
        }

        if (! $force && ! $this->isValidTransition($fromStatus, $toStatus)) {
            return OperationResult::failure("Invalid transition: {$fromStatus} -> {$toStatus}");
        }

        if ($force && ! in_array($toStatus, self::FORCE_CORRECTION_ALLOWED, true)) {
            $allowed = implode(', ', (array) self::FORCE_CORRECTION_ALLOWED);

            return OperationResult::failure("Force correction only allowed to terminal states: {$allowed}");
        }

        try {
            DB::transaction(function () use ($session, $fromStatus, $toStatus, $context) {
                $session->status = $toStatus;
                $session->end_reason = $context['end_reason'] ?? $session->end_reason;
                $session->linkedid = $context['linkedid'] ?? $session->linkedid;
                $session->channel = $context['channel'] ?? $session->channel;
                if (! empty($context['metadata'])) {
                    $meta = $session->metadata ?? [];
                    $session->metadata = array_merge($meta, $context['metadata']);
                }

                match ($toStatus) {
                    CallSession::STATUS_RINGING => $session->ringing_at ??= now(),
                    CallSession::STATUS_ANSWERED, CallSession::STATUS_IN_CALL => $session->answered_at ??= now(),
                    CallSession::STATUS_COMPLETED, CallSession::STATUS_FAILED, CallSession::STATUS_ABANDONED => $session->ended_at = now(),
                    default => null,
                };

                if ($session->ended_at && $session->answered_at) {
                    $session->call_duration_seconds = (int) $session->answered_at->diffInSeconds($session->ended_at);
                }

                $session->save();
                $this->syncLeadHopperByTerminalState($session, $toStatus);

                event(new CallStateChanged($session, $fromStatus, $toStatus));
            });

            return OperationResult::success();
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallStateService', 'Transition failed', [
                'session_id' => $session->id,
                'from' => $fromStatus,
                'to' => $toStatus,
                'error' => $e->getMessage(),
            ]);

            return OperationResult::failure($e->getMessage());
        }
    }

    public function isValidTransition(string $from, string $to): bool
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Force a stale active call to failed/abandoned (reconciliation).
     */
    public function forceStaleToTerminal(CallSession $session, string $toStatus = CallSession::STATUS_FAILED): OperationResult
    {
        return $this->transition($session, $toStatus, ['end_reason' => 'reconciliation_timeout'], true);
    }

    /**
     * Pre-answer statuses: call never connected, so hangup resolves to failed.
     */
    private const PRE_ANSWER_STATUSES = [
        CallSession::STATUS_DIALING,
        CallSession::STATUS_RINGING,
    ];

    /**
     * Record hangup (idempotent). Calls that never connected go to failed;
     * answered/in-progress calls go to completed.
     */
    public function recordHangup(CallSession $session, array $context = []): OperationResult
    {
        if ($session->isTerminal()) {
            return OperationResult::success(null, 'Already ended');
        }

        $ctx = array_merge($context, ['end_reason' => $context['end_reason'] ?? 'hangup']);

        $toStatus = in_array($session->status, self::PRE_ANSWER_STATUSES, true)
            ? CallSession::STATUS_FAILED
            : CallSession::STATUS_COMPLETED;

        return $this->transition($session, $toStatus, $ctx);
    }

    /**
     * Keep local lead hopper status aligned with terminal call outcomes.
     */
    private function syncLeadHopperByTerminalState(CallSession $session, string $toStatus): void
    {
        if (empty($session->lead_id) || empty($session->campaign_code)) {
            return;
        }

        $lead = LeadHopper::forCampaign((string) $session->campaign_code)
            ->where('lead_id', (string) $session->lead_id)
            ->first();
        if (! $lead) {
            return;
        }

        if ($toStatus === CallSession::STATUS_COMPLETED) {
            $lead->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return;
        }

        if ($toStatus === CallSession::STATUS_FAILED) {
            $lead->update([
                'status' => 'pending',
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'attempt_count' => (int) $lead->attempt_count + 1,
                'last_attempted_at' => now(),
            ]);
        }
    }
}
