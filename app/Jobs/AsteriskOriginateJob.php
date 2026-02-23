<?php

namespace App\Jobs;

use App\Services\Telephony\AsteriskAmiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AsteriskOriginateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of attempts. */
    public int $tries = 3;

    /** Seconds to wait before retry (first retry after 10s, then 30s). */
    public array $backoff = [10, 30];

    /** Job timeout in seconds (AMI can be slow). */
    public int $timeout = 60;

    public function __construct(
        public string $channel,
        public string $number,
        public string $callerId = ''
    ) {}

    public function handle(AsteriskAmiService $ami): void
    {
        $ami->originate($this->channel, $this->number, $this->callerId);
    }
}
