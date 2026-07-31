<?php

namespace App\Events;

use Carbon\CarbonInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardLayoutUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly CarbonInterface $updatedAt;

    public function __construct(
        public readonly string $campaignCode,
        public readonly string $action = 'layout_updated',
    ) {
        $this->updatedAt = now();
    }

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard.'.$this->campaignCode)];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.layout.updated';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'campaign' => $this->campaignCode,
            'action' => $this->action,
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
