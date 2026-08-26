<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'call_status_stats' => Http::response('VICICAMP|10|4|08-10|SALE-2,NA-8', 200),
                'agent_stats_export' => Http::response(
                    "user|full_name|calls|avg_talk_time\nagent-a|Agent A|4|00:01:00\nagent-a|Agent A|2|00:02:00",
                    200,
                ),
                'call_dispo_report' => Http::response(
                    "campaign|ingroup|NA|SALE\nVICICAMP|IN|8|2",
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
}
