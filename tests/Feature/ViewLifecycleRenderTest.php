<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
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

        view()->share('errors', new ViewErrorBag);
    }

    public function test_authenticated_layout_wires_shared_logout_cleanup_and_media_path_gate(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('href="#main-content"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('tabindex="-1"', false);
        $response->assertSee('aria-controls="sidebar"', false);
        $response->assertDontSee('sidebar-section-telephony', false);
        $response->assertDontSee('Toggle Telephony navigation', false);
        $response->assertSee('>Attendance</span>', false);
        $response->assertSee('window.crmGracefulLogout && window.crmGracefulLogout()', false);
        $response->assertSee('window.TelephonyMediaPath?.shouldUseSipMedia?.() === true', false);
        $response->assertSee('window.TelephonyMediaPath?.isDual?.() === true', false);
    }

    public function test_authenticated_layout_renders_accessible_actionable_notifications_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('class="notif-item w-full text-left"', false);
        $response->assertSee('Notifications could not be loaded.', false);
        $response->assertSee('Couldn’t refresh notifications.', false);
        $response->assertSee('Retry', false);
        $response->assertSee('role="status" aria-live="polite"', false);
        $response->assertSee('modal-title-notification-details', false);
        $response->assertSee('x-teleport="body"', false);
        $response->assertSee('aria-describedby="notification-detail-description"', false);
        $response->assertSee('Review the selected notification details.', false);
        $response->assertSee('Business hours:', false);
        $response->assertSee('x-text="detail?.date || \'\'"', false);
        $response->assertSee('x-text="detail?.range?.label || \'\'"', false);
        $response->assertSee('aria-label="Close dialog"', false);
        $response->assertSee('x-text="n.read ? \'Read\' : \'Unread\'"', false);
        $response->assertDontSee('n.campaign_code', false);
        $response->assertDontSee('n.form_type', false);
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
        $response->assertSee('window.crmSoftNav.refresh({ shouldDefer: shouldDeferRefresh })', false);
        $response->assertSee('echo.subscribeDashboardChannel?.(campaignCode, scheduleRefresh)', false);
        $response->assertSee('const fallbackIntervalMs = 30_000;', false);
        $response->assertSee('window.crmCharts?.register?.(chartGroup, elId, chart);', false);
        $response->assertSee('window.resizeCrmDashboardCharts?.()', false);
        $response->assertSee('Total Value:', false);
        $response->assertSee('Sales by Form', false);
        $response->assertDontSee('x-on:mouseenter="openSalesModal()"', false);
        $response->assertDontSee('x-on:mouseleave="scheduleSalesModalClose()"', false);
        $response->assertSee('x-transition:leave="transition ease-in duration-150"', false);
        $response->assertDontSee('Sales (24h)', false);
        $response->assertDontSee('Top agent (24h)', false);
        $response->assertDontSee('Calls (9h)', false);
    }

    public function test_quick_form_iframe_stays_blank_until_the_widget_is_open_or_split(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(':src="frameSrc || \'about:blank\'"', false);
        $response->assertSee(
            ':src="(open || isSplitActive()) && frameSrc ? frameSrc : \'about:blank\'"',
            false,
        );
    }

    public function test_dashboard_skips_apexcharts_loader_when_no_chart_can_render(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('const hasRenderableChartData = false;', $html);

        $guard = strpos($html, 'if (!hasRenderableChartData)');
        $loader = strpos($html, 'const ApexCharts = await window.ApexChartsLoader?.() ?? null;');

        $this->assertNotFalse($guard);
        $this->assertNotFalse($loader);
        $this->assertLessThan($loader, $guard);
        $this->assertStringContainsString('return;', substr($html, $guard, $loader - $guard));
    }

    public function test_dashboard_chart_resize_work_is_coalesced(): void
    {
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertIsString($source);
        $this->assertSame(1, substr_count($source, 'window.resizeCrmDashboardCharts?.()'));
        $this->assertStringNotContainsString('chart.resize();', $source);
        $this->assertStringNotContainsString(
            'setTimeout(() => window.resizeCrmDashboardCharts?.()',
            $source,
        );
        $this->assertStringNotContainsString("fontFamily: 'DM Sans, ui-sans-serif'", $source);
        $this->assertSame(
            2,
            substr_count(
                $source,
                'fontFamily: \'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif\'',
            ),
        );
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
        $this->assertStringContainsString("querySelector('#main-content')", $contents);
        $this->assertStringContainsString('preventScroll: true', $contents);
    }

    public function test_reports_preserve_last_good_data_when_a_refresh_is_unavailable(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('hasDashboardSnapshot', false);
        $response->assertSee('hasRealtimeSnapshot', false);
        $response->assertSee('Showing the last successful report snapshot', false);
        $response->assertSee("status: 'stale'", false);
        $response->assertSee('The last live snapshot could not be refreshed', false);
    }

    public function test_reports_omit_campaign_comparison_chart(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertDontSee('Campaign Comparison', false);
        $response->assertDontSee('chart-campaign-comparison', false);
        $response->assertSee('Call Volume Trend', false);
        $response->assertSee('Agent Performance', false);
        $response->assertSee('Disposition Pareto', false);
        $response->assertSee('reportSectionTitle', false);
        $response->assertSee('reportSectionDescription', false);
        $response->assertSee('class="crm-empty-state report-empty-state"', false);
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

    public function test_empty_state_component_renders_a_contextual_status_message(): void
    {
        $html = view('components.empty-state', [
            'icon' => 'chart-bar',
            'title' => 'No activity recorded yet',
            'description' => 'Charts will appear when qualifying activity is recorded.',
            'tone' => 'info',
        ])->render();

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('No activity recorded yet', $html);
        $this->assertStringContainsString('Charts will appear when qualifying activity is recorded.', $html);
        $this->assertStringContainsString('crm-empty-state--info', $html);
    }

    public function test_form_input_associates_help_text_with_the_control(): void
    {
        $html = Blade::render(
            '<x-form.input name="customer_name" label="Customer name" help="Use the customer\'s full name." />',
            ['errors' => new ViewErrorBag],
        );

        $this->assertStringContainsString('aria-describedby="field-customer_name-help"', $html);
        $this->assertStringContainsString('id="field-customer_name-help"', $html);
    }

    public function test_form_select_associates_help_text_with_the_control(): void
    {
        $html = Blade::render(
            '<x-form.select name="status" label="Status" help="Choose the current status." :options="[\'open\' => \'Open\']" />',
            ['errors' => new ViewErrorBag],
        );

        $this->assertStringContainsString('aria-describedby="field-status-help"', $html);
        $this->assertStringContainsString('id="field-status-help"', $html);
    }

    public function test_form_textarea_associates_help_text_with_the_control(): void
    {
        $html = Blade::render(
            '<x-form.textarea name="notes" label="Notes" help="Add context for the next user." />',
            ['errors' => new ViewErrorBag],
        );

        $this->assertStringContainsString('aria-describedby="field-notes-help"', $html);
        $this->assertStringContainsString('id="field-notes-help"', $html);
    }

    public function test_table_component_keeps_native_table_semantics_by_default(): void
    {
        $html = Blade::render(
            '<x-table.index caption="Example"><tbody><tr><td>Value</td></tr></tbody></x-table.index>',
            ['errors' => new ViewErrorBag],
        );

        $this->assertStringNotContainsString('role="grid"', $html);
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
