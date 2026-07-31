<?php

namespace Tests\Unit\Events;

use App\Events\DashboardLayoutUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class DashboardLayoutUpdatedTest extends TestCase
{
    public function test_event_broadcasts_a_campaign_scoped_layout_invalidation(): void
    {
        $event = new DashboardLayoutUpdated('mbsales');

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame('dashboard.layout.updated', $event->broadcastAs());
        $this->assertSame([
            'campaign' => 'mbsales',
            'action' => 'layout_updated',
            'updated_at' => $event->updatedAt->toIso8601String(),
        ], $event->broadcastWith());

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-dashboard.mbsales', $channels[0]->name);
    }
}
