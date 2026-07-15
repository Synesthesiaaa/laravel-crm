<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewLifecycleRenderTest extends TestCase
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

    public function test_authenticated_layout_wires_shared_logout_cleanup_and_media_path_gate(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('window.crmGracefulLogout && window.crmGracefulLogout()', false);
        $response->assertSee('window.TelephonyMediaPath?.shouldUseSipMedia?.() === true', false);
        $response->assertSee('window.TelephonyMediaPath?.isDual?.() === true', false);
    }

    public function test_dashboard_renders_soft_nav_chart_lifecycle_hooks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('window.crmSoftNav?.register?.(scope', false);
        $response->assertSee('window.crmSoftNav?.isRehydrating?.()', false);
        $response->assertSee('window.crmCharts?.register?.(chartGroup, elId, chart);', false);
        $response->assertSee('window.resizeCrmDashboardCharts?.()', false);
        $response->assertSee('Total value:', false);
        $response->assertSee('Sales (24h)', false);
        $response->assertSee('Top agent (24h)', false);
        $response->assertDontSee('Calls (9h)', false);
    }

    public function test_top_agent_stat_card_renders_sales_summary(): void
    {
        $html = view('components.stat-card', [
            'label' => 'Top agent (9h)',
            'value' => 'Alice',
            'secondary' => '2 sales · Total value: 125.50',
            'icon' => 'user',
            'color' => 'warning',
        ])->render();

        $this->assertStringContainsString('2 sales · Total value: 125.50', $html);
    }

    public function test_admin_dashboard_renders_soft_nav_chart_lifecycle_hooks(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        try {
            view()->share('activityTrend', [
                'labels' => ['Mon'],
                'values' => [1],
            ]);
            view()->share('topAgents', [
                'labels' => ['Agent A'],
                'values' => [1],
            ]);

            $response = $this->actingAs($user)
                ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
                ->get(route('admin.dashboard'));

            $response->assertOk();
            $response->assertSee('window.crmSoftNav?.register?.(scope', false);
            $response->assertSee('window.crmCharts?.register?.(chartGroup', false);
            $response->assertSee('window.crmCharts?.resizeGroup?.(chartGroup)', false);
        } finally {
            view()->share('activityTrend', null);
            view()->share('topAgents', null);
        }
    }
}
