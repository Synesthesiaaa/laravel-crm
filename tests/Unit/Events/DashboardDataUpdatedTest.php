<?php

namespace Tests\Unit\Events;

use App\Events\DashboardDataUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Tests\TestCase;

class DashboardDataUpdatedTest extends TestCase
{
    public function test_event_defers_a_minimal_campaign_scoped_payload_until_after_the_response(): void
    {
        $event = new DashboardDataUpdated('mbsales', 'ezycash', 42, 'submitted');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertNotInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertInstanceOf(ShouldRescue::class, $event);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame('deferred', $event->connection);
        $this->assertSame('dashboard.data.updated', $event->broadcastAs());
        $this->assertEquals([
            'campaign' => 'mbsales',
            'form_type' => 'ezycash',
            'record_id' => 42,
            'action' => 'submitted',
            'updated_at' => $event->updatedAt->toIso8601String(),
        ], $event->broadcastWith());
        $this->assertNotContains('sensitive-value', $event->broadcastWith());

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-dashboard.mbsales', $channels[0]->name);
    }
}
