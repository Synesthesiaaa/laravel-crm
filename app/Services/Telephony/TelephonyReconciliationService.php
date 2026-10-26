<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\UnmatchedAmiEvent;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles Laravel call_sessions with AMI events and optional external sources.
 * Auto-fixes mismatched states. Used by ReconcileCallStateJob.
 */
class TelephonyReconciliationService
{
    public function __construct(
        protected CallStateService $callStateService,
        protected CallUuidMappingService $mapping,
        protected TelephonyAlertService $alerts,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Run full reconciliation. Returns summary for monitoring.
     *
     * @return array{stale_corrected: int, unmatched_matched: int, stale_count: int, unmatched_count: int}
     */
    public function run(): array
    {
        $staleCorrected = $this->forceStaleCallsToTerminal();
        $unmatchedMatched = $this->retryUnmatchedAmiEvents();

        $staleAt = now()->subMinutes(CallStateService::STALE_CALL_MINUTES);
        $staleCount = CallSession::active()->where('dialed_at', '<', $staleAt)->count();
        $unmatchedCount = UnmatchedAmiEvent::unprocessed()
            ->whereIn('event', ['Hangup', 'HangupRequest', 'SoftHangupRequest'])
            ->where('received_at', '>=', now()->subHours(2))
            ->count();

        if ($staleCount > 0) {
            $this->telephonyLogger->warning('TelephonyReconciliationService', 'Stale calls still present after run', [
                'count' => $staleCount,
                'stale_corrected' => $staleCorrected,
            ]);
        }

        return [
            'stale_corrected' => $staleCorrected,
            'unmatched_matched' => $unmatchedMatched,
            'stale_count' => $staleCount,
            'unmatched_count' => $unmatchedCount,
        ];
    }

    protected function forceStaleCallsToTerminal(): int
    {
        $staleAt = now()->subMinutes(CallStateService::STALE_CALL_MINUTES);
        $stale = CallSession::active()
            ->where('dialed_at', '<', $staleAt)
            ->where(function ($query): void {
                $query->whereNull('remote_dial_outcome')
                    ->orWhere('remote_dial_outcome', '!=', 'unknown');
            })
            ->get();

        $corrected = 0;
        foreach ($stale as $session) {
            $result = $this->callStateService->forceStaleToTerminal($session, CallSession::STATUS_FAILED);
            if ($result->success) {
                $corrected++;
                $this->alerts->staleCorrected(
                    $session->id,
                    (int) $session->user_id,
                    $session->dialed_at?->toIso8601String() ?? '',
                );
            }
        }

        return $corrected;
    }

    protected function retryUnmatchedAmiEvents(): int
    {
        $events = UnmatchedAmiEvent::unprocessed()
            ->whereIn('event', ['Hangup', 'HangupRequest', 'SoftHangupRequest'])
            ->where('received_at', '>=', now()->subHours(2))
            ->where(function ($query): void {
                $query->whereNull('last_attempted_at')
                    ->orWhere('last_attempted_at', '<=', now()->subMinutes(5));
            })
            ->orderBy('received_at')
            ->limit(100)
            ->get();

        $matched = 0;
        foreach ($events as $ev) {
            $session = $this->mapping->findSessionForHangup(
                $ev->linkedid,
                $ev->channel,
                $ev->payload ?? [],
            );

            $sessionId = DB::transaction(function () use ($ev, $session): ?int {
                $event = UnmatchedAmiEvent::query()->whereKey($ev->id)->lockForUpdate()->first();
                if (! $event || $event->processed) {
                    return null;
                }

                $event->attempt_count = (int) $event->attempt_count + 1;
                $event->last_attempted_at = now();

                if (! $session) {
                    $event->last_error_code = 'NO_CORRELATED_SESSION';
                    $event->save();

                    return null;
                }

                $this->mapping->attachAsteriskIdentifiers($session, $event->linkedid, $event->channel);
                $result = $this->callStateService->recordHangup($session, [
                    'end_reason' => 'reconciliation',
                    'linkedid' => $event->linkedid,
                    'channel' => $event->channel,
                    'remote_outcome_resolved' => true,
                ]);

                if (! $result->success) {
                    $event->last_error_code = 'CALL_TRANSITION_REJECTED';
                    $event->save();

                    return null;
                }

                $event->processed = true;
                $event->last_error_code = null;
                $event->save();

                return (int) $session->id;
            });

            if ($sessionId !== null) {
                $matched++;
                $this->alerts->unmatchedAmiProcessed($ev->id, $sessionId);
            }
        }

        UnmatchedAmiEvent::where('processed', true)
            ->where('updated_at', '<', now()->subDays(7))
            ->delete();

        return $matched;
    }
}
