<?php

namespace App\Events;

use Carbon\CarbonInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly CarbonInterface $updatedAt;

    public function __construct(
        public readonly string $campaignCode,
        public readonly string $formType,
        public readonly int $recordId,
        public readonly string $action,
    ) {
        $this->updatedAt = now();
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard.'.$this->campaignCode)];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.data.updated';
    }

    /**
     * @return array<string, int|string>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign' => $this->campaignCode,
            'form_type' => $this->formType,
            'record_id' => $this->recordId,
            'action' => $this->action,
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
