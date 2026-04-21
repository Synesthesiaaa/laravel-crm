<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires when an inbound or dialer call is answered and lead data should
 * be "popped" on the agent screen. ShouldBroadcastNow for zero-delay delivery.
 */
class InboundCallReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $phoneNumber,
        public readonly ?int $leadId = null,
        public readonly ?string $clientName = null,
        public readonly ?string $campaignCode = null,
        public readonly array $leadData = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inbound.call.received';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'phone_number' => $this->phoneNumber,
            'lead_id' => $this->leadId,
            'client_name' => $this->clientName,
            'campaign_code' => $this->campaignCode,
            'lead_data' => $this->leadData,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
