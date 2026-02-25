<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly CallSession $session,
        public readonly string $fromStatus,
        public readonly string $toStatus
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('telephony.supervisor'),
        ];

        if ($this->session->user_id) {
            $channels[] = new PrivateChannel('App.Models.User.'.$this->session->user_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name (Pusher-compatible).
     */
    public function broadcastAs(): string
    {
        return 'call.state.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id'    => $this->session->id,
            'user_id'       => $this->session->user_id,
            'from_status'   => $this->fromStatus,
            'to_status'     => $this->toStatus,
            'phone_number'  => $this->session->phone_number,
            'campaign_code' => $this->session->campaign_code,
            'answered_at'   => $this->session->answered_at?->toIso8601String(),
            'ended_at'      => $this->session->ended_at?->toIso8601String(),
            'timestamp'     => now()->toIso8601String(),
        ];
    }
}
