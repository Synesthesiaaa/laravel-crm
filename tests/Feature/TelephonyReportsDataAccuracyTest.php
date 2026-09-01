<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelephonyReportsDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_use_vicidial_headers_and_aggregate_all_mapped_campaigns(): void
    {
        config()->set('vicidial.report_disposition_groups', [
            'contacted' => ['SALE'],
            'qualified' => ['SALE'],
            'successful' => ['SALE'],
        ]);

        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A', 'camp_b');
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'DISABLED',
            'is_enabled' => true,
            'status' => CampaignVicidialMapping::STATUS_DISABLED,
        ]);
        $this->clearCampaignCache();

        Http::fake(function ($request) {
            return match ($request->data()['function'] ?? null) {
                'call_status_stats' => Http::response(implode("\n", [
                    'campaign_id/ingroup|total calls|human answered calls|hourly breakdown|status breakdown',
                    'camp_a/IN|100|50|08-100|SALE-40,NA-60',
                    'CAMP_B/IN|900|90|09-900|SALE-90,NA-810',
                    'OTHER/IN|9999|9999|10-9999|SALE-9999',
                ]), 200),
                'agent_stats_export' => Http::response(implode("\n", [
                    'campaign_id|user|full_name|calls|total_talk_time|ready_time|other_time',
                    'camp_a|agent-a|Agent A|100|1000|600|300',
                    'CAMP_B|agent-a|Agent A|900|9000|1800|1200',
                    'OTHER|agent-b|Agent B|9999|9999|9999|9999',
                ]), 200),
                'call_dispo_report' => Http::response(implode("\n", [
                    'campaign|ingroup|NA|SALE',
                    'camp_a|IN|80 (80%)|20 (20%)',
                    'CAMP_B|IN|810 (90%)|90 (10%)',
                    'OTHER|IN|0|9999',
                ]), 200),
                default => Http::response('', 200),
            };
        });

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign', 'campaign_name' => 'CRM Campaign'])
            ->getJson(route('api.reports.dashboard', [
                'campaign' => 'crm-campaign',
                'query_date' => '2026-08-20',
                'end_date' => '2026-08-26',
                'timezone' => 'Asia/Manila',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_calls', 1000)
            ->assertJsonPath('data.summary.answered_calls', 140)
            ->assertJsonPath('data.summary.answer_rate', 14)
            ->assertJsonPath('data.summary.contact_rate', 11)
            ->assertJsonPath('data.call_volume.labels', array_map(static fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT), range(0, 23)))
            ->assertJsonPath('data.call_volume.values.8', 100)
            ->assertJsonPath('data.call_volume.values.9', 900)
            ->assertJsonPath('data.status_totals.SALE', 130)
            ->assertJsonPath('data.status_totals.NA', 870)
            ->assertJsonPath('data.status_state', 'data')
            ->assertJsonPath('data.campaign_scope.campaign_codes', ['CAMP_A', 'camp_b'])
            ->assertJsonCount(2, 'data.campaigns')
            ->assertJsonPath('data.campaigns.0.total_calls', 100)
            ->assertJsonPath('data.campaigns.0.answer_rate', 50)
            ->assertJsonPath('data.campaigns.0.contact_rate', 20)
            ->assertJsonPath('data.campaigns.1.total_calls', 900)
            ->assertJsonPath('data.campaigns.1.answer_rate', 10)
            ->assertJsonPath('data.campaigns.1.contact_rate', 10)
            ->assertJsonPath('data.disposition_summary.total_calls', 1000)
            ->assertJsonPath('data.disposition_summary.contacted_calls', 110)
            ->assertJsonPath('data.dispositions.labels.0', 'NA')
            ->assertJsonPath('data.dispositions.values.0', 890)
            ->assertJsonPath('data.dispositions.percentages.0', 89)
            ->assertJsonPath('data.dispositions.percentages.1', 11)
            ->assertJsonCount(2, 'data.disposition_rows')
            ->assertJsonPath('data.disposition_rows.0.total_calls', 100)
            ->assertJsonPath('data.disposition_rows.1.total_calls', 900)
            ->assertJsonPath('data.time_distribution.talk_seconds', 10000)
            ->assertJsonPath('data.time_distribution.ready_seconds', 2400)
            ->assertJsonPath('data.time_distribution.other_seconds', 1500)
            ->assertJsonPath('data.time_distribution.states.ready_seconds', 'data')
            ->assertJsonPath('data.time_distribution.states.other_seconds', 'data');

        $this->assertNotSame('unavailable', $response->json('data.availability.status'));
        $this->assertStringNotContainsString('OTHER', json_encode($response->json('data')) ?: '');

        Http::assertSent(function ($request): bool {
            return ($request->data()['function'] ?? null) === 'agent_stats_export'
                && ($request->data()['datetime_start'] ?? null) === '2026-08-20+00:00:00'
                && ($request->data()['datetime_end'] ?? null) === '2026-08-26+23:59:59';
        });
        Http::assertSent(function ($request): bool {
            return ($request->data()['function'] ?? null) === 'call_status_stats'
                && ($request->data()['campaigns'] ?? null) === 'CAMP_A-camp_b';
        });
    }

    public function test_historical_requests_use_vicidial_campaign_delimiter_for_all_mapped_campaigns(): void
    {
        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A', 'CAMP_B');
        $this->clearCampaignCache();

        Http::fake(function ($request) {
            $function = $request->data()['function'] ?? null;
            if (in_array($function, ['call_status_stats', 'call_dispo_report'], true)
                && ($request->data()['campaigns'] ?? null) !== 'CAMP_A-CAMP_B') {
                return Http::response('ERROR: invalid campaign scope', 200);
            }

            return match ($function) {
                'call_status_stats' => Http::response(implode("\n", [
                    'campaign_id/ingroup|total calls|human answered calls|hourly breakdown|status breakdown',
                    'CAMP_A/IN|100|50|08-100|SALE-50',
                    'CAMP_B/IN|900|90|09-900|SALE-90',
                ]), 200),
                'agent_stats_export' => Http::response(implode("\n", [
                    'campaign_id|user|full_name|calls|total_talk_time',
                    'CAMP_A|agent-a|Agent A|100|1000',
                    'CAMP_B|agent-a|Agent A|900|9000',
                ]), 200),
                'call_dispo_report' => Http::response(implode("\n", [
                    'campaign|ingroup|SALE',
                    'CAMP_A|IN|50',
                    'CAMP_B|IN|90',
                ]), 200),
                default => Http::response('', 200),
            };
        });

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign'])
            ->getJson(route('api.reports.dashboard', [
                'campaign' => 'crm-campaign',
                'query_date' => '2026-08-20',
                'end_date' => '2026-08-26',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_calls', 1000)
            ->assertJsonPath('data.summary.answered_calls', 140)
            ->assertJsonPath('data.campaign_scope.campaign_codes', ['CAMP_A', 'CAMP_B'])
            ->assertJsonCount(2, 'data.campaigns');

        Http::assertSent(function ($request): bool {
            return ($request->data()['function'] ?? null) === 'call_status_stats'
                && ($request->data()['campaigns'] ?? null) === 'CAMP_A-CAMP_B';
        });
        Http::assertSent(function ($request): bool {
            return ($request->data()['function'] ?? null) === 'call_dispo_report'
                && ($request->data()['campaigns'] ?? null) === 'CAMP_A-CAMP_B';
        });
        Http::assertSent(function ($request): bool {
            return ($request->data()['function'] ?? null) === 'agent_stats_export'
                && ! array_key_exists('campaign_id', $request->data());
        });
    }

    public function test_successful_empty_reports_are_unavailable_not_confirmed_zero(): void
    {
        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A');
        $this->clearCampaignCache();
        Http::fake(fn () => Http::response('SUCCESS', 200));

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign'])
            ->getJson(route('api.reports.dashboard', ['campaign' => 'crm-campaign']));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_calls', null)
            ->assertJsonPath('data.summary.answered_calls', null)
            ->assertJsonPath('data.summary.answer_rate', null)
            ->assertJsonPath('data.call_volume.state', 'empty')
            ->assertJsonPath('data.status_state', 'empty')
            ->assertJsonPath('data.disposition_summary.state', 'empty')
            ->assertJsonPath('data.availability.status', 'unavailable');
    }

    public function test_explicit_zero_rows_remain_confirmed_zero(): void
    {
        config()->set('vicidial.report_disposition_groups', [
            'contacted' => ['SALE'],
            'qualified' => [],
            'successful' => [],
        ]);
        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A');
        $this->clearCampaignCache();

        Http::fake(function ($request) {
            return match ($request->data()['function'] ?? null) {
                'call_status_stats' => Http::response("campaign_id|total calls|human answered calls|hourly breakdown|status breakdown\nCAMP_A|0|0|00-0|SALE-0", 200),
                'agent_stats_export' => Http::response("user|campaign|calls|total_talk_time\nagent-a|CAMP_A|0|0", 200),
                'call_dispo_report' => Http::response("campaign|ingroup|SALE\nCAMP_A|IN|0", 200),
                default => Http::response('', 200),
            };
        });

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign'])
            ->getJson(route('api.reports.dashboard', ['campaign' => 'crm-campaign']));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_calls', 0)
            ->assertJsonPath('data.summary.answer_rate', 0)
            ->assertJsonPath('data.call_volume.state', 'confirmed_zero')
            ->assertJsonPath('data.status_state', 'confirmed_zero')
            ->assertJsonPath('data.disposition_summary.total_calls', 0)
            ->assertJsonPath('data.disposition_summary.contacted_calls', 0)
            ->assertJsonPath('data.availability.status', 'live');
    }

    public function test_disposition_totals_rows_and_columns_are_not_reported_as_dispositions(): void
    {
        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A');
        $this->clearCampaignCache();

        Http::fake(function ($request) {
            return match ($request->data()['function'] ?? null) {
                'call_status_stats' => Http::response("campaign_id|total calls|human answered calls|hourly breakdown|status breakdown\nCAMP_A|100|20|08-100|SALE-20,NA-80", 200),
                'agent_stats_export' => Http::response("user|campaign|calls|total_talk_time\nagent-a|CAMP_A|100|100", 200),
                'call_dispo_report' => Http::response(implode("\n", [
                    'campaign|ingroup|TOTAL CALLS|NA|SALE',
                    'CAMP_A|IN|100|80|20',
                    'TOTAL CALLS|IN|100|80|20',
                ]), 200),
                default => Http::response('', 200),
            };
        });

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign'])
            ->getJson(route('api.reports.dashboard', ['campaign' => 'crm-campaign']));

        $response->assertOk()
            ->assertJsonPath('data.dispositions.labels', ['NA', 'SALE'])
            ->assertJsonPath('data.dispositions.values', [80, 20])
            ->assertJsonPath('data.dispositions.percentages', [80, 20])
            ->assertJsonPath('data.disposition_summary.total_calls', 100)
            ->assertJsonPath('data.disposition_rows.0.total_calls', 100)
            ->assertJsonPath('data.disposition_rows.0.top_disposition', 'NA');

        $this->assertStringNotContainsString('TOTAL CALLS', json_encode($response->json('data.dispositions')) ?: '');
    }

    public function test_secondary_campaign_filter_cannot_escape_the_crm_scope(): void
    {
        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A');
        $this->clearCampaignCache();
        Http::fake();

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign'])
            ->getJson(route('api.reports.dashboard', [
                'campaign' => 'crm-campaign',
                'campaigns' => 'OTHER',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.availability.status', 'unavailable')
            ->assertJsonPath('data.summary.total_calls', null)
            ->assertJsonPath('data.campaigns', []);
        Http::assertNothingSent();
    }

    public function test_malformed_report_rows_are_marked_as_parsing_failures(): void
    {
        [$campaign, $server] = $this->campaignAndServer();
        $this->mapCampaign($campaign, $server, 'CAMP_A');
        $this->clearCampaignCache();

        Http::fake(function ($request) {
            return match ($request->data()['function'] ?? null) {
                'call_status_stats' => Http::response("campaign_id|total calls|human answered calls\nCAMP_A|not-a-number|4", 200),
                'agent_stats_export' => Http::response("user|campaign|calls\nagent-a|CAMP_A|10", 200),
                'call_dispo_report' => Http::response("campaign|ingroup|SALE\nCAMP_A|IN|10", 200),
                default => Http::response('', 200),
            };
        });

        $response = $this->actingAs($this->reportUser())
            ->withSession(['campaign' => 'crm-campaign'])
            ->getJson(route('api.reports.dashboard', ['campaign' => 'crm-campaign']));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_calls', null)
            ->assertJsonPath('data.availability.status', 'degraded')
            ->assertJsonPath('data.availability.sources.call_status.state', 'parse_failure')
            ->assertJsonPath('data.availability.sources.call_status.status', 'unavailable');

        $this->assertStringNotContainsString('"total_calls":0', $response->getContent());
    }

    private function campaignAndServer(): array
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-campaign', 'name' => 'CRM Campaign']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'crm-campaign',
            'api_url' => 'https://reports.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);

        return [$campaign, $server];
    }

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

    private function reportUser(): User
    {
        return User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);
    }

    private function clearCampaignCache(): void
    {
        $this->app->make(\App\Services\CampaignService::class)->clearCampaignsCache();
    }
}
