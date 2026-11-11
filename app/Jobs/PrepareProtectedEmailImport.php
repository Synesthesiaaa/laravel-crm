<?php

namespace App\Jobs;

use App\Services\Email\ProtectedEmailImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepareProtectedEmailImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public string $importId)
    {
        $this->onConnection(config('email_campaigns.preparation_connection', 'email-preparation'))->onQueue(config('email_campaigns.preparation_queue', 'email-preparation'));
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(ProtectedEmailImportService $imports): void
    {
        $imports->process($this->importId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(ProtectedEmailImportService::class)->interrupted($this->importId);
    }
}
