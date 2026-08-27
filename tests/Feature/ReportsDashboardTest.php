<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_dashboard_sections_and_collapsed_debug_area(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB Sales',
            ])
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Historical Performance');
        $response->assertSee('Live — rolling window');
        $response->assertSee('Today — midnight to now');
        $response->assertSee('chart-live-activity');
        $response->assertSee('refreshInFlight');
        $response->assertSee('Disposition Scope');
        $response->assertSee('Hide system dispositions');
        $response->assertSee('Call Volume Trend');
        $response->assertSee('Agent Performance');
        $response->assertSee('Disposition Pareto');
        $response->assertSee('Campaign Comparison');
        $response->assertSee('Call Funnel');
        $response->assertSee('Agent Time Distribution');
        $response->assertSee('Debug / Raw VICIdial Output');
        $response->assertSee('<details', false);
        $response->assertDontSee('role="tablist"', false);
        $response->assertDontSee('Recording Browser');
    }

    public function test_reports_page_requires_higher_role_access(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_AGENT,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB Sales',
            ])
            ->get(route('reports.index'));

        $response->assertForbidden();
    }

    public function test_historical_dashboard_returns_campaign_scoped_aggregated_data(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://reports-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
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

        $user = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);
        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
            ->getJson(route('api.reports.dashboard', [
                'campaign' => 'campaign-a',
                'query_date' => '2026-08-20',
                'end_date' => '2026-08-26',
                'comparison' => 'none',
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.crm_campaign', 'campaign-a')
            ->assertJsonPath('data.summary.total_calls', 10)
            ->assertJsonPath('data.summary.answered_calls', 4)
            ->assertJsonPath('data.status_totals.SALE', 2)
            ->assertJsonPath('data.agents.0.calls', 6)
            ->assertJsonPath('data.availability.status', 'live');

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://reports-a.example/non_agent_api.php')
                && ($request->data()['function'] ?? null) === 'call_status_stats'
                && ($request->data()['query_date'] ?? null) === '2026-08-20'
                && ($request->data()['end_date'] ?? null) === '2026-08-26';
        });
    }

    public function test_live_and_today_reports_reuse_one_normalized_snapshot_and_keep_scopes_explicit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
            Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
            VicidialServer::factory()->create([
                'campaign_code' => 'campaign-a',
                'api_url' => 'https://reports-a.example/agc/api.php',
                'api_user' => 'report-user',
                'api_pass' => 'report-pass',
            ]);
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
                    'call_status_stats' => Http::response('VICICAMP|1|1|12-1|SALE-1', 200),
                    default => Http::response('', 200),
                };
            });

            $user = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);
            $live = $this->actingAs($user)
                ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
                ->getJson(route('api.reports.live', ['campaign' => 'campaign-a']));

            $live->assertOk()
                ->assertJsonPath('data.mode', 'live')
                ->assertJsonPath('data.rolling.calls_initiated', 1)
                ->assertJsonPath('data.rolling.answered', 1)
                ->assertJsonPath('data.rolling.average_talk_seconds', 60)
                ->assertJsonPath('data.time_scope.label', 'Last 15 minutes');

            $today = $this->getJson(route('api.reports.today', ['campaign' => 'campaign-a']));
            $today->assertOk()
                ->assertJsonPath('data.mode', 'today')
                ->assertJsonPath('data.today.total_calls', 1)
                ->assertJsonPath('data.today.answered', 1)
                ->assertJsonPath('data.today.label', 'Midnight → now');

            Http::assertSentCount(6);
        } finally {
            Carbon::setTestNow();
        }
    }
}
