<?php

namespace Tests\Unit\Events;

use App\Events\InboundCallReceived;
use App\Events\VicidialAgentEvent;
use App\Models\User;
use Tests\TestCase;

class TelephonyBroadcastChannelsTest extends TestCase
{
    public function test_inbound_call_broadcasts_to_frontend_user_channel(): void
    {
        $event = new InboundCallReceived(
            userId: 42,
            phoneNumber: '63999111222',
        );

        $channels = $this->channelNames($event->broadcastOn());

        $this->assertContains('private-App.Models.User.42', $channels);
        $this->assertContains('private-agent.42', $channels);
    }

    public function test_vicidial_agent_event_broadcasts_to_frontend_user_channel(): void
    {
        $event = new VicidialAgentEvent(
            userId: 42,
            event: 'state_ready',
        );

        $channels = $this->channelNames($event->broadcastOn());

        $this->assertContains('private-App.Models.User.42', $channels);
        $this->assertContains('private-agent.42', $channels);
        $this->assertContains('private-telephony.supervisor', $channels);
    }

    public function test_user_notifications_use_existing_frontend_private_channel(): void
    {
        $user = new User;
        $user->id = 42;

        $this->assertSame('App.Models.User.42', $user->receivesBroadcastNotificationsOn());
    }

    /**
     * @param  array<int, mixed>  $channels
     * @return array<int, string>
     */
    private function channelNames(array $channels): array
    {
        return array_map(static fn ($channel) => (string) $channel->name, $channels);
    }
}
