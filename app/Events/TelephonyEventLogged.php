<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelephonyEventLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $eventType,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('telephony.supervisor')];
    }

    public function broadcastAs(): string
    {
        return 'telephony.event.logged';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'severity' => $this->severity,
            'message' => $this->message,
            'context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
