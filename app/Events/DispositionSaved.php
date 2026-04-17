<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispositionSaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $campaignCode,
        public readonly string $agent,
        public readonly string $dispositionCode,
        public readonly ?int $leadId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('telephony.supervisor')];
    }

    public function broadcastAs(): string
    {
        return 'disposition.saved';
    }

    public function broadcastWith(): array
    {
        return [
            'agent' => $this->agent,
            'campaign_code' => $this->campaignCode,
            'disposition_code' => $this->dispositionCode,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
