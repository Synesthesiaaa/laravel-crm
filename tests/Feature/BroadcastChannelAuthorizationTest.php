<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_authorize_an_active_dashboard_campaign(): void
    {
        Campaign::factory()->create(['code' => 'mbsales', 'is_active' => true]);
        $callback = Broadcast::getChannels()->get('dashboard.{campaign}');

        $this->assertIsCallable($callback);
        $this->assertTrue($callback(User::factory()->make(), 'mbsales'));
    }

    public function test_dashboard_channel_rejects_an_inactive_or_unknown_campaign(): void
    {
        Campaign::factory()->create(['code' => 'inactive', 'is_active' => false]);
        $callback = Broadcast::getChannels()->get('dashboard.{campaign}');

        $this->assertIsCallable($callback);
        $user = User::factory()->make();
        $this->assertFalse($callback($user, 'inactive'));
        $this->assertFalse($callback($user, 'unknown'));
    }
}
