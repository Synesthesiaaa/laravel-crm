<?php

namespace Tests\Unit\Events;

use App\Events\ActivityLogCreated;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\TestCase;

class ActivityLogCreatedTest extends TestCase
{
    public function test_broadcasts_the_normalized_entry_on_the_private_activity_channel(): void
    {
        $event = new ActivityLogCreated(42, ['id' => 42, 'action' => 'updated']);

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame('private-activity-log', $event->broadcastOn()[0]->name);
        $this->assertSame('activity.log.created', $event->broadcastAs());
        $this->assertSame(['entry' => ['id' => 42, 'action' => 'updated']], $event->broadcastWith());
    }
}
