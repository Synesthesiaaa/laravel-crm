<?php

namespace Tests\Feature\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Models\Campaign;
use App\Models\DashboardLayout;
use App\Models\User;
use App\Services\DashboardLayoutService;
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

    public function test_applying_dashboard_layout_preserves_existing_custom_sales_attribution(): void
    {
        $sales = [
            'mode' => 'custom',
            'forms' => [[
                'form_code' => 'ezycash',
                'amount_field' => null,
                'trigger' => 'form',
                'conditions' => [],
            ]],
        ];
        app(DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            array_keys(DashboardLayoutService::sectionDefinitions()),
            ['welcome'],
            $sales,
            true,
            ['enabled' => false],
        );

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => ['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'],
                'visible_sections' => ['forms', 'welcome'],
                'amounts' => ['enabled' => '1'],
            ])
            ->assertRedirect(route('admin.dashboard', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $layout = DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertSame($sales, $layout['sales']);
        $this->assertSame(['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'], array_keys($layout['sections']));
        $this->assertTrue($layout['sections']['forms']['visible']);
        $this->assertTrue($layout['amounts']['enabled']);
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

    public function test_admin_can_select_a_campaign_without_changing_the_active_campaign_session(): void
    {
        Campaign::factory()->create([
            'code' => 'pjli',
            'name' => 'PJLI',
            'display_order' => 1,
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.dashboard', ['campaign' => 'pjli']));

        $response->assertOk()
            ->assertSee('Campaign: <span class="font-semibold text-[var(--color-action)]">PJLI</span>', false)
            ->assertDontSee('Sales Attribution');
        $this->assertSame('mbsales', session('campaign'));
    }

    public function test_team_leader_dashboard_does_not_prepare_admin_only_layout_editor_data(): void
    {
        $teamLeader = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);

        $response = $this->actingAs($teamLeader)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewMissing('dashboardLayout')
            ->assertViewMissing('dashboardSections')
            ->assertViewMissing('salesConfiguration')
            ->assertViewMissing('salesEditorForms');
    }
}
