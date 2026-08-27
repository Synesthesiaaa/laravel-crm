<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\HistoricalTelephonyReportService;
use App\Services\Telephony\ReportingService;
use App\Support\OperationResult;
use Mockery;
use Tests\TestCase;

class HistoricalTelephonyReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_dashboard_aggregates_duplicate_agent_rows_and_returns_configured_funnel_data(): void
    {
        config()->set('vicidial.report_disposition_groups', [
            'contacted' => ['SALE'],
            'qualified' => ['SALE'],
            'successful' => ['SALE'],
        ]);

        $reporting = Mockery::mock(ReportingService::class);
        $reporting->shouldReceive('historicalSnapshot')
            ->once()
            ->andReturn($this->snapshot(
                [
                    ['campaign-a', '10', '4', '08-4,09-6', 'SALE-2,NA-8'],
                    ['campaign-b', '2', '1', '09-2', 'SALE-1,NA-1'],
                    ['TOTAL', '12', '5', '08-4,09-8', 'SALE-3,NA-9'],
                ],
                [
                    ['user', 'campaign', 'full_name', 'calls', 'avg_talk_time', 'pause_time'],
                    ['agent-a', 'campaign-a', 'Agent A', '4', '00:01:00', '00:10:00'],
                    ['agent-a', 'campaign-a', 'Agent A', '2', '00:02:00', '00:05:00'],
                ],
                [
                    ['campaign', 'ingroup', 'NA', 'SALE'],
                    ['campaign-a', 'IN', '8', '2'],
                ],
            ));

        $service = new HistoricalTelephonyReportService($reporting, $this->legacyScopeResolver());
        $data = $service->dashboard(
            User::factory()->make(),
            'crm-campaign',
            [
                'campaigns' => '---ALL---',
                'query_date' => '2026-08-20',
                'end_date' => '2026-08-26',
                'comparison' => 'none',
                'disposition_scope' => 'all',
            ],
        );

        $this->assertSame(12, $data['summary']['total_calls']);
        $this->assertSame(5, $data['summary']['answered_calls']);
        $this->assertSame(1, $data['summary']['agents_with_activity']);
        $this->assertSame(6, $data['agents'][0]['calls']);
        $this->assertSame(480, $data['agents'][0]['total_talk_time_seconds']);
        $this->assertSame(5, $data['funnel'][1]['value']);
        $this->assertSame(2, $data['funnel'][2]['value']);
        $this->assertSame(3, $data['status_totals']['SALE']);
        $this->assertSame(['campaign-a', 'campaign-b'], array_column($data['campaigns'], 'campaign'));
    }

    public function test_funnel_is_empty_without_disposition_classification_configuration(): void
    {
        config()->set('vicidial.report_disposition_groups', [
            'contacted' => [],
            'qualified' => [],
            'successful' => [],
        ]);

        $reporting = Mockery::mock(ReportingService::class);
        $reporting->shouldReceive('historicalSnapshot')
            ->once()
            ->andReturn($this->snapshot(
                [['campaign-a', '10', '4', '', 'SALE-4']],
                [['user', 'campaign', 'calls'], ['agent-a', 'campaign-a', '10']],
                [['campaign', 'ingroup', 'SALE'], ['campaign-a', 'IN', '4']],
            ));

        $data = (new HistoricalTelephonyReportService($reporting, $this->legacyScopeResolver()))->dashboard(
            User::factory()->make(),
            'crm-campaign',
            ['query_date' => '2026-08-20', 'end_date' => '2026-08-26'],
        );

        $this->assertSame([], $data['funnel']);
    }

    public function test_rate_comparison_is_reported_in_percentage_points(): void
    {
        $reporting = Mockery::mock(ReportingService::class);
        $reporting->shouldReceive('historicalSnapshot')
            ->twice()
            ->andReturn(
                $this->snapshot(
                    [['campaign-a', '10', '4', '', '']],
                    [['user', 'campaign', 'calls', 'avg_talk_time'], ['agent-a', 'campaign-a', '10', '10']],
                    [['campaign', 'ingroup', 'SALE'], ['campaign-a', 'IN', '1']],
                ),
                $this->snapshot(
                    [['campaign-a', '10', '2', '', '']],
                    [['user', 'campaign', 'calls', 'avg_talk_time'], ['agent-a', 'campaign-a', '10', '10']],
                    [['campaign', 'ingroup', 'SALE'], ['campaign-a', 'IN', '1']],
                ),
            );

        $service = new HistoricalTelephonyReportService($reporting, $this->legacyScopeResolver());
        $data = $service->dashboard(
            User::factory()->make(),
            'crm-campaign',
            [
                'query_date' => '2026-08-20',
                'end_date' => '2026-08-26',
                'comparison' => 'previous_period',
                'disposition_scope' => 'all',
            ],
        );

        $this->assertSame(20.0, $data['comparison']['metrics']['answer_rate']['change']);
        $this->assertSame('rate', $data['comparison']['metrics']['answer_rate']['unit']);
        $this->assertSame('2026-08-13', $data['comparison']['period']['start']);
        $this->assertSame('2026-08-19', $data['comparison']['period']['end']);
    }

    public function test_answer_rate_is_weighted_from_raw_campaign_totals(): void
    {
        $reporting = Mockery::mock(ReportingService::class);
        $reporting->shouldReceive('historicalSnapshot')
            ->once()
            ->andReturn($this->snapshot(
                [
                    ['campaign-a', '100', '50', '', ''],
                    ['campaign-b', '900', '90', '', ''],
                ],
                [['user', 'campaign', 'calls'], ['agent-a', 'campaign-a', '100']],
                [['campaign', 'ingroup', 'SALE'], ['campaign-a', 'IN', '10']],
            ));

        $data = (new HistoricalTelephonyReportService($reporting, $this->legacyScopeResolver()))->dashboard(
            User::factory()->make(),
            'crm-campaign',
            ['query_date' => '2026-08-20', 'end_date' => '2026-08-26'],
        );

        $this->assertSame(1000, $data['summary']['total_calls']);
        $this->assertSame(140, $data['summary']['answered_calls']);
        $this->assertSame(14.0, $data['summary']['answer_rate']);
    }

    public function test_system_disposition_scope_is_applied_to_pareto_and_contact_rate(): void
    {
        config()->set('vicidial.report_system_disposition_codes', ['SYS']);
        config()->set('vicidial.report_disposition_groups', [
            'contacted' => ['SALE'],
            'qualified' => [],
            'successful' => [],
        ]);

        $reporting = Mockery::mock(ReportingService::class);
        $reporting->shouldReceive('historicalSnapshot')
            ->once()
            ->andReturn($this->snapshot(
                [['campaign-a', '10', '4', '', 'SALE-3,SYS-7']],
                [['user', 'campaign', 'calls'], ['agent-a', 'campaign-a', '10']],
                [['campaign', 'ingroup', 'SYS', 'SALE'], ['campaign-a', 'IN', '7', '3']],
            ));

        $data = (new HistoricalTelephonyReportService($reporting, $this->legacyScopeResolver()))->dashboard(
            User::factory()->make(),
            'crm-campaign',
            [
                'query_date' => '2026-08-20',
                'end_date' => '2026-08-26',
                'disposition_scope' => 'exclude_system',
            ],
        );

        $this->assertSame(30.0, $data['summary']['contact_rate']);
        $this->assertSame(['SALE'], $data['dispositions']['labels']);
        $this->assertNotContains('SYS', array_column($data['disposition_rows'][0]['metrics'], 'label'));
    }

    /**
     * @param  array<int, array<int, string>>  $callStatus
     * @param  array<int, array<int, string>>  $agentStats
     * @param  array<int, array<int, string>>  $dispositions
     * @return array<string, OperationResult>
     */
    private function snapshot(array $callStatus, array $agentStats, array $dispositions): array
    {
        return [
            'call_status' => OperationResult::success(['rows' => $callStatus]),
            'agent_stats' => OperationResult::success(['rows' => $agentStats]),
            'call_dispo' => OperationResult::success(['rows' => $dispositions]),
        ];
    }

    private function legacyScopeResolver(): CrmCampaignVicidialScopeResolver
    {
        $campaign = new Campaign(['code' => 'crm-campaign', 'name' => 'CRM Campaign']);
        $campaign->id = 1;
        $campaign->exists = true;
        $server = new VicidialServer(['server_name' => 'Test Server', 'campaign_code' => 'crm-campaign']);
        $server->id = 1;
        $server->exists = true;
        $mappings = collect([
            new CampaignVicidialMapping(['vicidial_campaign_code' => 'campaign-a', 'is_enabled' => true, 'status' => 'active']),
            new CampaignVicidialMapping(['vicidial_campaign_code' => 'campaign-b', 'is_enabled' => true, 'status' => 'active']),
        ]);

        $resolver = Mockery::mock(CrmCampaignVicidialScopeResolver::class);
        $resolver->shouldReceive('resolve')
            ->with('crm-campaign')
            ->andReturn(new \App\Services\Telephony\VicidialCampaignScope($campaign, $server, $mappings));

        return $resolver;
    }
}
