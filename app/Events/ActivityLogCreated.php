<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogCreated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'deferred';

    /**
     * @param  array<string, mixed>  $entry
     */
    public function __construct(public readonly int $activityId, public readonly array $entry) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('activity-log')];
    }

    public function broadcastAs(): string
    {
        return 'activity.log.created';
    }

    /**
     * @return array{entry: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['entry' => $this->entry];
    }
}
