<?php

namespace Tests\Feature\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminDashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_admin_can_apply_a_campaign_dashboard_layout(): void
    {
        Event::fake([DashboardLayoutUpdated::class]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.dashboard-layout.update'), [
                'section_order' => ['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'],
                'visible_sections' => ['forms', 'welcome'],
            ]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Dashboard layout applied.');
        $this->assertDatabaseHas('dashboard_layouts', ['campaign_code' => 'mbsales']);
        Event::assertDispatched(DashboardLayoutUpdated::class);
    }

    public function test_team_leader_cannot_apply_a_dashboard_layout(): void
    {
        $teamLeader = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);

        $this->actingAs($teamLeader)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.dashboard-layout.update'), [
                'section_order' => ['welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'forms', 'quick_links'],
                'visible_sections' => ['welcome'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_user_dashboard_renders_saved_layout_for_active_campaign(): void
    {
        app(\App\Services\DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            ['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'],
            ['forms', 'welcome'],
        );
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-dashboard-section="forms"', false)
            ->assertDontSee('data-dashboard-section="activity"', false);
    }
}
