<?php

namespace App\Jobs;

use App\Services\Telephony\TelephonySyncOutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTelephonySyncOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public int $outboxId)
    {
        $this->onConnection('database')->onQueue('telephony-sync');
    }

    public function handle(TelephonySyncOutboxService $outbox): void
    {
        $outbox->process($this->outboxId);
    }
}
