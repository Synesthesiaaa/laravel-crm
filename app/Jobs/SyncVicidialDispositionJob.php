<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Services\Telephony\VicidialDispositionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVicidialDispositionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(public int $callSessionId)
    {
        // Run after the HTTP response without depending on Redis/Horizon.
        $this->onConnection('deferred');
    }

    public function handle(VicidialDispositionSyncService $service): void
    {
        $session = CallSession::query()->find($this->callSessionId);
        if (! $session || $session->disposition_code === null) {
            return;
        }

        $service->syncDispositionToVicidial($session);
    }
}
