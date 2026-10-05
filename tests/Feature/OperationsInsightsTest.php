<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialServer;
use App\Services\DashboardLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationsInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_operations_insights_with_professional_neutral_labels(): void
    {
        Campaign::factory()->create([
            'code' => 'campaign-a',
            'name' => 'Campaign A',
        ]);

        $response = $this->get('/operations-insights');

        $response->assertOk()
            ->assertSee('Operations Insights')
            ->assertSee('Campaign Performance &amp; Reporting', false)
            ->assertSee('Overview')
            ->assertSee('Call Reports')
            ->assertSee('Past Performance')
            ->assertSee('Live Activity')
            ->assertSee('Today')
            ->assertSee('x-data="operationsInsightsDashboard', false)
            ->assertSee('id="operations-campaign"', false)
            ->assertSee('operations-activity-chart', false)
            ->assertSee('operations-report-agent-table', false)
            ->assertSee('refreshAll()', false)
            ->assertSee("sectionVisible('kpis')", false)
            ->assertSee("sectionVisible('activity')", false)
            ->assertSee("sectionVisible('leaderboard')", false)
            ->assertSee("sectionVisible('campaign_report')", false)
            ->assertDontSee('Read-only', false)
            ->assertDontSee('Read only', false)
            ->assertDontSee('No login required')
            ->assertDontSee('Quality Analyst')
            ->assertDontSee('Operations Manager')
            ->assertDontSee('Sign out')
            ->assertDontSee('Debug and Raw VICIdial Output');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $viewSource = file_get_contents(resource_path('views/operations-insights.blade.php'));
        $this->assertIsString($viewSource);
        $this->assertStringContainsString('resources/js/operations-insights.js', $viewSource);
        $this->assertStringNotContainsString('resources/js/app.js', $viewSource);

        $this->get('/readonly-dashboard')->assertNotFound();
    }

    public function test_guest_can_load_historical_dashboard_and_report_data_with_server_reporting_credentials(): void
    {
        $campaign = Campaign::factory()->create([
            'code' => 'campaign-a',
            'name' => 'Campaign A',
        ]);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://reports-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'TESTCAMP',
        ]);
        $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();

        Http::fake(function ($request) {
            return match ($request->data()['function'] ?? null) {
                'call_status_stats' => Http::response(file_get_contents(base_path('tests/Fixtures/Vicidial/call_status_stats.txt')), 200),
                'agent_stats_export' => Http::response(file_get_contents(base_path('tests/Fixtures/Vicidial/agent_stats_export.txt')), 200),
                'call_dispo_report' => Http::response(file_get_contents(base_path('tests/Fixtures/Vicidial/call_dispo_report.txt')), 200),
                default => Http::response('', 200),
            };
        });

        $response = $this->getJson('/api/operations-insights?campaign=campaign-a&mode=historical&query_date=2026-08-20&end_date=2026-08-26&comparison=none');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.context.campaign.code', 'campaign-a')
            ->assertJsonPath('data.reports.mode', 'historical')
            ->assertJsonPath('data.reports.summary.total_calls', 10)
            ->assertJsonPath('data.reports.summary.answered_calls', 4)
            ->assertJsonPath('data.reports.agents.0.calls', 6)
            ->assertJsonStructure([
                'data' => [
                    'dashboard' => ['kpis', 'summary', 'activity', 'campaign_report'],
                    'reports',
                ],
            ])
            ->assertJsonMissingPath('data.reports.campaign_scope');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://reports-a.example/non_agent_api.php')
                && ($request->data()['user'] ?? null) === 'report-user'
                && ($request->data()['pass'] ?? null) === 'report-pass';
        });
    }

    public function test_guest_can_load_live_read_only_metrics_without_raw_vicidial_snapshot_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
            $campaign = Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
            $server = VicidialServer::factory()->create([
                'campaign_code' => 'campaign-a',
                'api_url' => 'https://reports-a.example/agc/api.php',
                'api_user' => 'report-user',
                'api_pass' => 'report-pass',
            ]);
            foreach (['campaign-a', 'VICICAMP'] as $code) {
                CampaignVicidialMapping::factory()->create([
                    'campaign_id' => $campaign->id,
                    'vicidial_server_id' => $server->id,
                    'vicidial_campaign_code' => $code,
                ]);
            }
            CallSession::factory()->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_COMPLETED,
                'dialed_at' => now()->subMinutes(5),
                'answered_at' => now()->subMinutes(5)->addSeconds(10),
                'ended_at' => now()->subMinutes(5)->addSeconds(70),
                'call_duration_seconds' => 60,
                'disposition_code' => 'SALE',
                'disposition_at' => now()->subMinutes(4),
            ]);
            $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();

            Http::fake(function ($request) {
                return match ($request->data()['function'] ?? null) {
                    'logged_in_agents' => Http::response("user|status\n", 200),
                    'agent_stats_export' => Http::response("user|calls\n", 200),
                    'call_status_stats' => Http::response('VICICAMP|9|4|12-9|SALE-9', 200),
                    default => Http::response('', 200),
                };
            });

            $response = $this->getJson('/api/operations-insights?campaign=campaign-a&mode=live');

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.reports.mode', 'live')
                ->assertJsonPath('data.reports.rolling.calls_initiated', 1)
                ->assertJsonPath('data.reports.rolling.answered', 1)
                ->assertJsonPath('data.reports.today.total_calls', 9)
                ->assertJsonPath('data.reports.today.answered', 4)
                ->assertJsonMissingPath('data.reports.server')
                ->assertJsonMissingPath('data.reports.snapshot')
                ->assertJsonMissingPath('data.reports.active_calls');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_public_dashboard_does_not_expose_amount_fields_when_campaign_amounts_are_disabled(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        $sections = array_keys(DashboardLayoutService::sectionDefinitions());
        $this->app->make(DashboardLayoutService::class)->saveForCampaign(
            'campaign-a',
            $sections,
            $sections,
            amountConfig: ['enabled' => false],
        );
        $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();

        $response = $this->getJson('/api/operations-insights?campaign=campaign-a&mode=historical');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.layout.amounts.enabled', false)
            ->assertJsonMissingPath('data.dashboard.kpis.sales_amount')
            ->assertJsonMissingPath('data.dashboard.kpis.top_agent_sales_amount')
            ->assertJsonMissingPath('data.dashboard.summary.amount_definition')
            ->assertJsonMissingPath('data.dashboard.summary.summary.current.amount')
            ->assertJsonMissingPath('data.dashboard.summary.summary.previous.amount')
            ->assertJsonMissingPath('data.dashboard.summary.comparison.amount')
            ->assertJsonMissingPath('data.dashboard.campaign_report.totals.daily.total_amount')
            ->assertJsonMissingPath('data.dashboard.campaign_report.totals.month_to_date.total_amount');
    }

    public function test_public_dashboard_rejects_historical_ranges_larger_than_31_days(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();

        $this->getJson('/api/operations-insights?campaign=campaign-a&mode=historical&query_date=2026-08-01&end_date=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }
}
