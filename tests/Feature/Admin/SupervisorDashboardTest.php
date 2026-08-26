<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_does_not_render_sample_agents(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.supervisor'))
            ->assertOk()
            ->assertSee('Live supervisor data unavailable')
            ->assertSee('VICIdial Routing')
            ->assertSee('routing.configured')
            ->assertDontSee('actionPending')
            ->assertDontSee('monitorAgent')
            ->assertDontSee('whisperAgent')
            ->assertDontSee('forcePause')
            ->assertDontSee('forceLogout')
            ->assertSee('Answer Rate')
            ->assertSee('Live state:')
            ->assertSee('Agent timing:')
            ->assertSee('Call totals:')
            ->assertSee('VICIdial real-time report')
            ->assertSee('VICIdial agent stats')
            ->assertSee('VICIdial daily report')
            ->assertSee('avgHandleTime')
            ->assertSee('refreshInFlight')
            ->assertSee('notificationPending')
            ->assertSee('campaign: this.routing.campaign_code')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('Juan Cruz');
    }

    public function test_supervisor_dashboard_can_select_a_crm_campaign_without_using_vicidial_campaign(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        Campaign::factory()->create(['code' => 'campaign-b', 'name' => 'Campaign B']);
        $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();

        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'campaign-a',
                'campaign_name' => 'Campaign A',
                'vicidial_campaign' => 'softcamp',
            ])
            ->get(route('admin.supervisor', ['campaign' => 'campaign-b']))
            ->assertOk()
            ->assertSee("supervisorDashboard('campaign-b')", false)
            ->assertSee('id="supervisor-campaign"', false)
            ->assertSee('This CRM campaign selects the VICIdial server.', false);
    }
}
