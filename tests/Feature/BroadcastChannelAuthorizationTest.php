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

    public function test_activity_log_channel_is_restricted_to_super_admins(): void
    {
        $callback = Broadcast::getChannels()->get('activity-log');

        $this->assertIsCallable($callback);
        $this->assertTrue($callback(User::factory()->make(['role' => User::ROLE_SUPER_ADMIN])));
        $this->assertFalse($callback(User::factory()->make(['role' => User::ROLE_ADMIN])));
    }
}
