<?php

namespace App\Services\Telephony;

use App\Events\CallStateChanged;
use App\Models\CallSession;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallStateService
{
    /**
     * Valid state transitions: from => [to, ...]
     */
    protected const VALID_TRANSITIONS = [
        CallSession::STATUS_DIALING => [
            CallSession::STATUS_RINGING,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
        ],
        CallSession::STATUS_RINGING => [
            CallSession::STATUS_ANSWERED,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
        ],
        CallSession::STATUS_ANSWERED => [
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_FAILED,
        ],
        CallSession::STATUS_IN_CALL => [
            CallSession::STATUS_ON_HOLD,
            CallSession::STATUS_TRANSFERRING,
            CallSession::STATUS_COMPLETED,
            CallSession::STATUS_FAILED,
        ],
        CallSession::STATUS_ON_HOLD => [
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_FAILED,
        ],
        CallSession::STATUS_TRANSFERRING => [
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_COMPLETED,
            CallSession::STATUS_FAILED,
        ],
    ];

    /**
     * Transitions that bypass validation (force correction, reconciliation).
     * Only terminal states can be forced onto active calls.
     */
    protected const FORCE_CORRECTION_ALLOWED = [
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
            Log::channel('telephony')->info('CallStateService: Ignoring transition from terminal state', [
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
            return OperationResult::failure("Force correction only allowed to: " . implode(', ', self::FORCE_CORRECTION_ALLOWED));
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

                event(new CallStateChanged($session, $fromStatus, $toStatus));
            });

            return OperationResult::success();
        } catch (\Throwable $e) {
            Log::channel('telephony')->error('CallStateService: Transition failed', [
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
     * Record hangup (idempotent). Transitions from any active state to completed.
     */
    public function recordHangup(CallSession $session, array $context = []): OperationResult
    {
        if ($session->isTerminal()) {
            return OperationResult::success(null, 'Already ended');
        }

        $ctx = array_merge($context, ['end_reason' => $context['end_reason'] ?? 'hangup']);

        return $this->transition($session, CallSession::STATUS_COMPLETED, $ctx);
    }
}
