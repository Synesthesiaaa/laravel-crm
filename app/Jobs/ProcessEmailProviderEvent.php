<?php

namespace App\Jobs;

use App\Services\Email\PostmarkEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEmailProviderEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $eventId) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(PostmarkEventService $events): void
    {
        $events->process($this->eventId);
    }
}
