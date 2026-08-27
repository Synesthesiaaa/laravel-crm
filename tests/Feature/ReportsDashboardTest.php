<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function mapCampaign(Campaign $campaign, VicidialServer $server, string ...$codes): void
    {
        foreach ($codes as $code) {
            CampaignVicidialMapping::factory()->create([
                'campaign_id' => $campaign->id,
                'vicidial_server_id' => $server->id,
                'vicidial_campaign_code' => $code,
            ]);
        }
    }

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
        $campaign = Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://reports-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);
        $this->mapCampaign($campaign, $server, 'TESTCAMP');
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

    public function test_historical_dashboard_filters_unmapped_campaigns_and_weights_combined_rates(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://reports-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);
        $this->mapCampaign($campaign, $server, 'CAMP_A', 'CAMP_B');
        $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();

        Http::fake(function ($request) {
            return match ($request->data()['function'] ?? null) {
                'call_status_stats' => Http::response(
                    "CAMP_A|100|50|08-100|SALE-50\nCAMP_B|900|90|09-900|SALE-90\nOTHER|10000|10000|10-10000|SALE-10000",
                    200,
                ),
                'agent_stats_export' => Http::response(
                    "user|campaign|full_name|calls|total_talk_time\nagent-a|CAMP_A|Agent A|100|1000\nagent-a|CAMP_B|Agent A|900|9000\nagent-b|OTHER|Agent B|10000|10000",
                    200,
                ),
                'call_dispo_report' => Http::response(
                    "campaign|ingroup|SALE\nCAMP_A|IN|50\nCAMP_B|IN|90\nOTHER|IN|10000",
                    200,
                ),
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
            ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_calls', 1000)
            ->assertJsonPath('data.summary.answered_calls', 140)
            ->assertJsonPath('data.summary.answer_rate', 14)
            ->assertJsonCount(2, 'data.campaigns')
            ->assertJsonPath('data.campaigns.0.campaign', 'CAMP_A')
            ->assertJsonPath('data.agents.0.calls', 1000)
            ->assertJsonPath('data.disposition_rows.0.campaign', 'CAMP_A')
            ->assertJsonPath('data.disposition_rows.1.campaign', 'CAMP_B');

        Http::assertSent(function ($request): bool {
            return ($request->data()['function'] ?? null) === 'call_status_stats'
                && ($request->data()['campaigns'] ?? null) === 'CAMP_A|CAMP_B';
        });
    }

    public function test_live_and_today_reports_reuse_one_normalized_snapshot_and_keep_scopes_explicit(): void
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
            $this->mapCampaign($campaign, $server, 'campaign-a', 'VICICAMP');
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
                ->assertJsonPath('data.today.total_calls', 9)
                ->assertJsonPath('data.today.answered', 4)
                ->assertJsonPath('data.today.source', 'vicidial')
                ->assertJsonPath('data.today.available', true)
                ->assertJsonPath('data.today.label', 'Midnight → now');

            Http::assertSentCount(6);
        } finally {
            Carbon::setTestNow();
        }
    }
}
