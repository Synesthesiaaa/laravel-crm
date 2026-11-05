<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Reuses dashboard refresh subscriptions without invalidating unrelated form/sales caches. */
class VerifiedCallMetricsUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public string $connection = 'deferred';

    public function __construct(public readonly string $campaignCode) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard.'.$this->campaignCode)];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.data.updated';
    }

    public function broadcastWith(): array
    {
        return ['campaign' => $this->campaignCode, 'form_type' => 'telephony_metrics',
            'record_id' => 0, 'action' => 'updated', 'updated_at' => now()->toIso8601String()];
    }
}
