<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Models\UnmatchedAmiEvent;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\CallUuidMappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileCallStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(CallStateService $callStateService, CallUuidMappingService $mapping): void
    {
        $this->forceStaleCallsToFailed($callStateService);
        $this->retryUnmatchedAmiEvents($callStateService, $mapping);
    }

    protected function forceStaleCallsToFailed(CallStateService $callStateService): void
    {
        $staleAt = now()->subMinutes(CallStateService::STALE_CALL_MINUTES);
        $stale = CallSession::active()
            ->where('dialed_at', '<', $staleAt)
            ->get();

        foreach ($stale as $session) {
            $result = $callStateService->forceStaleToTerminal($session, CallSession::STATUS_FAILED);
            if ($result->success) {
                Log::channel('telephony')->warning('ReconcileCallState: Forced stale call to failed', [
                    'session_id' => $session->id,
                    'user_id' => $session->user_id,
                    'dialed_at' => $session->dialed_at?->toIso8601String(),
                ]);
            }
        }
    }

    /**
     * Retry matching unmatched AMI hangup events (e.g. session created after event).
     */
    protected function retryUnmatchedAmiEvents(CallStateService $callStateService, CallUuidMappingService $mapping): void
    {
        $events = UnmatchedAmiEvent::unprocessed()
            ->whereIn('event', ['Hangup', 'HangupRequest', 'SoftHangupRequest'])
            ->where('received_at', '>=', now()->subHours(2))
            ->limit(100)
            ->get();

        foreach ($events as $ev) {
            $session = $mapping->findSessionForHangup(
                $ev->linkedid,
                $ev->channel,
                $ev->payload ?? [],
            );

            if ($session) {
                $mapping->attachAsteriskIdentifiers($session, $ev->linkedid, $ev->channel);
                $callStateService->recordHangup($session, [
                    'end_reason' => 'reconciliation',
                    'linkedid' => $ev->linkedid,
                    'channel' => $ev->channel,
                ]);
                $ev->update(['processed' => true]);
                Log::channel('telephony')->info('ReconcileCallState: Matched and processed previously unmatched AMI event', [
                    'event_id' => $ev->id,
                    'session_id' => $session->id,
                ]);
            }
        }

        UnmatchedAmiEvent::where('processed', true)
            ->where('updated_at', '<', now()->subDays(7))
            ->delete();
    }
}
