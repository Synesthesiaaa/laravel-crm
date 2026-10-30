<?php

namespace App\Jobs;

use App\Services\Imports\UserImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessUserImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 120;

    public function __construct(public string $importId, public string $phase)
    {
        $this->onConnection('user-imports')->onQueue('user-imports');
    }

    public function handle(UserImportService $imports): void
    {
        if ($imports->process($this->importId, $this->phase)) {
            $this->release(1);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(UserImportService::class)->fail($this->importId, $this->phase);
    }
}
