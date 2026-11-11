<?php

namespace App\Jobs;

use App\Services\Email\AudienceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepareEmailAudience implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public function __construct(public string $importId)
    {
        $this->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'));
    }

    public function handle(AudienceService $audiences): void
    {
        if ($audiences->process($this->importId)) {
            $this->release(1);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(AudienceService::class)->fail($this->importId, 'The import worker stopped. Resume preparation or upload the list again.');
    }
}
