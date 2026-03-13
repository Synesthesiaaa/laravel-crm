<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast ViciDial agent-level events that don't map to CallStateChanged.
 * Examples: state_ready, state_paused, logged_in, 3way_start, park_started.
 * Uses ShouldBroadcastNow for sub-second delivery.
 */
class VicidialAgentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $event,
        public readonly string $message = '',
        public readonly array $extra = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.'.$this->userId),
            new PrivateChannel('telephony.supervisor'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vicidial.agent.event';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'   => $this->userId,
            'event'     => $this->event,
            'message'   => $this->message,
            'extra'     => $this->extra,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
