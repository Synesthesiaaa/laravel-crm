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

    public function test_authenticated_layout_exposes_accessible_shell_landmarks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Skip to main content', false)
            ->assertSee('href="#main-content"', false)
            ->assertSee('aria-controls="sidebar"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('id="main-content"', false);
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
        $response->assertSee('window.crmSoftNav.refresh()', false);
        $response->assertSee('echo.subscribeDashboardChannel?.(campaignCode, scheduleRefresh)', false);
        $response->assertSee('const fallbackIntervalMs = 30_000;', false);
        $response->assertSee('window.crmCharts?.register?.(chartGroup, elId, chart);', false);
        $response->assertSee('window.resizeCrmDashboardCharts?.()', false);
        $response->assertSee('Total value:', false);
        $response->assertSee('Sales by form', false);
        $response->assertSee('x-on:mouseenter="openSalesModal()"', false);
        $response->assertSee('x-on:mouseleave="scheduleSalesModalClose()"', false);
        $response->assertSee('x-transition:leave="transition ease-in duration-150"', false);
        $response->assertDontSee('Sales (24h)', false);
        $response->assertDontSee('Top agent (24h)', false);
        $response->assertDontSee('Calls (9h)', false);
    }

    public function test_soft_navigation_script_handles_marked_get_forms(): void
    {
        $contents = file_get_contents(resource_path('js/soft-navigate.js'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('form[data-soft-nav]', $contents);
        $this->assertStringContainsString('new FormData(form)', $contents);
        $this->assertStringContainsString('softNavigate(url.href, { push: true })', $contents);
        $this->assertStringContainsString('dataset.campaign', $contents);
        $this->assertStringContainsString('crm-campaign-changed', $contents);
        $this->assertStringContainsString('campaignName', $contents);
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

    public function test_shared_visual_system_preserves_established_brand_tokens(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('--color-primary:           #e91e8c;', $css);
        $this->assertStringContainsString('--color-surface:           #0a0a0a;', $css);
    }

    public function test_icon_component_supports_meaningful_labels_without_decorative_semantics(): void
    {
        $html = view('components.icon', [
            'name' => 'chart-bar',
            'label' => 'Analytics',
        ])->render();

        $this->assertStringContainsString('aria-label="Analytics"', $html);
        $this->assertStringNotContainsString('aria-hidden="true"', $html);
    }

    public function test_icon_component_uses_consistent_decorative_defaults(): void
    {
        $html = view('components.icon', [
            'name' => 'chart-bar',
        ])->render();

        $this->assertStringContainsString('class="crm-icon w-5 h-5"', $html);
        $this->assertStringContainsString('stroke-width="1.75"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('focusable="false"', $html);
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
