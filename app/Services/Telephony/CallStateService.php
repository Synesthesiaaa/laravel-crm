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
            CallSession::STATUS_IN_CALL,
            CallSession::STATUS_FAILED,
            CallSession::STATUS_ABANDONED,
            CallSession::STATUS_COMPLETED,
        ],
        CallSession::STATUS_RINGING => [
            CallSession::STATUS_ANSWERED,
            CallSession::STATUS_IN_CALL,
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
     * @param  array{end_reason?: string, linkedid?: string, channel?: string, metadata?: array, remote_dial_outcome?: string, remote_outcome_resolved?: bool}  $context
     */
    public function transition(CallSession $session, string $toStatus, array $context = [], bool $force = false): OperationResult
    {
        return $this->applyTransition($session, $toStatus, $context, $force);
    }

    /**
     * Mark a call connected when an authoritative telephony event confirms answer.
     *
     * @param  array{linkedid?: string, channel?: string, metadata?: array}  $context
     */
    public function markConnected(CallSession $session, array $context = []): OperationResult
    {
        return $this->transition($session, CallSession::STATUS_IN_CALL, array_merge($context, [
            'remote_outcome_resolved' => true,
        ]));
    }

    /**
     * Apply a transition against the locked persisted row rather than the possibly stale model.
     *
     * @param  string|callable(CallSession): string  $toStatus
     * @param  array{end_reason?: string, linkedid?: string, channel?: string, metadata?: array, assume_connected?: bool, remote_dial_outcome?: string, remote_outcome_resolved?: bool}  $context
     */
    private function applyTransition(
        CallSession $session,
        string|callable $toStatus,
        array $context = [],
        bool $force = false,
        string $terminalMessage = 'Already terminal',
    ): OperationResult {
        try {
            $outcome = DB::transaction(function () use ($session, $toStatus, $context, $force, $terminalMessage): array {
                $persisted = CallSession::query()
                    ->whereKey($session->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $persisted) {
                    return ['result' => OperationResult::failure('Call session no longer exists.'), 'session' => null];
                }

                $fromStatus = (string) $persisted->status;
                $resolvedToStatus = is_callable($toStatus) ? $toStatus($persisted) : $toStatus;

                if ($persisted->isTerminal()) {
                    return ['result' => OperationResult::success(null, $terminalMessage), 'session' => $persisted];
                }

                if ($fromStatus === $resolvedToStatus) {
                    return ['result' => OperationResult::success(null, 'Already in state'), 'session' => $persisted];
                }

                if ($force && ! in_array($resolvedToStatus, self::FORCE_CORRECTION_ALLOWED, true)) {
                    $allowed = implode(', ', self::FORCE_CORRECTION_ALLOWED);

                    return [
                        'result' => OperationResult::failure("Force correction only allowed to terminal states: {$allowed}"),
                        'session' => null,
                    ];
                }

                if (! $force && ! $this->isValidTransition($fromStatus, $resolvedToStatus)) {
                    return [
                        'result' => OperationResult::failure("Invalid transition: {$fromStatus} -> {$resolvedToStatus}"),
                        'session' => null,
                    ];
                }

                $persisted->status = $resolvedToStatus;
                $persisted->end_reason = $context['end_reason'] ?? $persisted->end_reason;
                $persisted->linkedid = $context['linkedid'] ?? $persisted->linkedid;
                $persisted->channel = $context['channel'] ?? $persisted->channel;
                if (isset($context['remote_dial_outcome'])) {
                    $persisted->remote_dial_outcome = $context['remote_dial_outcome'];
                    $persisted->remote_dial_outcome_at = now();
                } elseif (($context['remote_outcome_resolved'] ?? false) && $persisted->remote_dial_outcome === 'unknown') {
                    $persisted->remote_dial_outcome = 'accepted';
                    $persisted->remote_dial_outcome_at = now();
                }
                if (! empty($context['metadata'])) {
                    $persisted->metadata = array_merge($persisted->metadata ?? [], $context['metadata']);
                }

                match ($resolvedToStatus) {
                    CallSession::STATUS_RINGING => $persisted->ringing_at ??= now(),
                    CallSession::STATUS_ANSWERED, CallSession::STATUS_IN_CALL => $persisted->answered_at ??= now(),
                    CallSession::STATUS_COMPLETED, CallSession::STATUS_FAILED, CallSession::STATUS_ABANDONED => $persisted->ended_at = now(),
                    default => null,
                };

                if (($context['assume_connected'] ?? false) && $resolvedToStatus === CallSession::STATUS_COMPLETED && $persisted->answered_at === null) {
                    $persisted->answered_at = $persisted->ringing_at ?? $persisted->dialed_at ?? now();
                }

                if ($persisted->ended_at && $persisted->answered_at) {
                    $persisted->call_duration_seconds = (int) $persisted->answered_at->diffInSeconds($persisted->ended_at);
                }

                $persisted->save();
                $this->syncLeadHopperByTerminalState($persisted, $resolvedToStatus);
                event(new CallStateChanged($persisted, $fromStatus, $resolvedToStatus));

                return ['result' => OperationResult::success(), 'session' => $persisted];
            });

            if ($outcome['session'] instanceof CallSession && $outcome['result']->success) {
                $session->setRawAttributes($outcome['session']->getAttributes(), true);
            }

            return $outcome['result'];
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallStateService', 'Transition failed', [
                'session_id' => $session->id,
                'to' => is_string($toStatus) ? $toStatus : 'hangup_target',
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
     * Preserve an ambiguous remote dial result without releasing the active
     * reservation. A callback may have advanced the persisted session while
     * the HTTP request was waiting, so always inspect and update the locked row.
     */
    public function markOutboundDialOutcomeUnknown(CallSession $session): OperationResult
    {
        try {
            $persisted = DB::transaction(function () use ($session): ?CallSession {
                $persisted = CallSession::query()->whereKey($session->getKey())->lockForUpdate()->first();
                if (! $persisted) {
                    return $persisted;
                }

                if ($persisted->status !== CallSession::STATUS_DIALING) {
                    if ($persisted->remote_dial_outcome === null || $persisted->remote_dial_outcome === 'unknown') {
                        $persisted->remote_dial_outcome = 'accepted';
                        $persisted->remote_dial_outcome_at = now();
                        $persisted->save();
                    }

                    return $persisted;
                }

                $persisted->remote_dial_outcome = 'unknown';
                $persisted->remote_dial_outcome_at = now();
                $persisted->save();

                return $persisted;
            });

            if ($persisted) {
                $session->setRawAttributes($persisted->getAttributes(), true);
            }

            return $persisted
                ? OperationResult::success(['session_id' => $persisted->id, 'outcome' => $persisted->remote_dial_outcome])
                : OperationResult::failure('Call session no longer exists.');
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('CallStateService', 'Unable to preserve unknown remote dial outcome', [
                'session_id' => $session->id,
                'exception_class' => $e::class,
            ]);

            return OperationResult::failure('Unable to record the remote dial outcome.');
        }
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
        $ctx = array_merge($context, ['end_reason' => $context['end_reason'] ?? 'hangup']);

        return $this->applyTransition(
            $session,
            static fn (CallSession $persisted): string => in_array($persisted->status, self::PRE_ANSWER_STATUSES, true)
                ? CallSession::STATUS_FAILED
                : CallSession::STATUS_COMPLETED,
            $ctx,
            terminalMessage: 'Already ended',
        );
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
