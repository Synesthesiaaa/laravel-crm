<?php

namespace App\Jobs;

use App\Services\Telephony\TelephonyReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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

    public function handle(TelephonyReconciliationService $reconciliation): void
    {
        $summary = $reconciliation->run();
        Log::channel('telephony')->info('ReconcileCallState completed', $summary);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('reconcile-call-state'))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }
}
