<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\ReportingService;
use App\Services\Telephony\VicidialCampaignScope;
use App\Services\Telephony\VicidialNonAgentApiService;
use App\Support\OperationResult;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_agent_stats_maps_campaign_and_date_filters_to_vicidial_parameters(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        $user = User::factory()->make();
        $nonAgentApi = Mockery::mock(VicidialNonAgentApiService::class);

        $nonAgentApi->shouldReceive('execute')
            ->never();
        $nonAgentApi->shouldReceive('executeOnServer')
            ->once()
            ->with(
                $user,
                Mockery::type(VicidialServer::class),
                'mbsales',
                'agent_stats_export',
                Mockery::on(function (array $params): bool {
                    return $params['datetime_start'] === '2026-06-01+00:00:00'
                        && $params['datetime_end'] === '2026-06-05+23:59:59'
                        && $params['campaign_id'] === 'TESTCAMP'
                        && $params['group_by_campaign'] === 'YES'
                        && $params['stage'] === 'pipe'
                        && $params['header'] === 'YES';
                }),
                true,
                [],
            )
            ->andReturn(OperationResult::success(['rows' => []]));

        $service = new ReportingService($nonAgentApi, $this->scopeResolver());

        $result = $service->agentStats($user, 'mbsales', [
            'campaigns' => 'TESTCAMP',
            'query_date' => '2026-06-01',
            'end_date' => '2026-06-05',
        ]);

        $this->assertTrue($result->success);
    }

    public function test_call_status_stats_forwards_bounded_http_options(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $user = User::factory()->make();
        $nonAgentApi = Mockery::mock(VicidialNonAgentApiService::class);

        $nonAgentApi->shouldReceive('executeOnServer')
            ->once()
            ->with(
                $user,
                Mockery::type(VicidialServer::class),
                'campaign-a',
                'call_status_stats',
                [
                    'campaigns' => 'campaign-a',
                    'query_date' => '2026-08-26',
                ],
                true,
                [
                    'connect_timeout' => 1,
                    'timeout' => 3,
                    'retry_times' => 0,
                ],
            )
            ->andReturn(OperationResult::success(['rows' => []]));

        $service = new ReportingService($nonAgentApi, $this->scopeResolver());

        $result = $service->callStatusStats(
            $user,
            'campaign-a',
            ['campaigns' => '---ALL---', 'query_date' => '2026-08-26'],
            ['connect_timeout' => 1, 'timeout' => 3, 'retry_times' => 0],
        );

        $this->assertTrue($result->success);
    }

    public function test_supervisor_snapshot_requests_supported_server_wide_reports_with_bounded_options(): void
    {
        $user = User::factory()->make();
        $nonAgentApi = Mockery::mock(VicidialNonAgentApiService::class);
        $httpOptions = ['connect_timeout' => 1, 'timeout' => 3, 'retry_times' => 0];

        $nonAgentApi->shouldReceive('executeBatch')
            ->once()
            ->with(
                $user,
                'campaign-a',
                Mockery::on(function (array $requests): bool {
                    return $requests['logged_agents']['function'] === 'logged_in_agents'
                        && $requests['logged_agents']['params']['campaigns'] === 'campaign-a'
                        && $requests['agent_performance']['function'] === 'agent_stats_export'
                        && $requests['agent_performance']['params']['datetime_start'] === '2026-08-26+00:00:00'
                        && $requests['agent_performance']['params']['datetime_end'] === '2026-08-26+23:59:59'
                        && $requests['agent_performance']['params']['group_by_campaign'] === 'NO'
                        && $requests['agent_performance']['params']['time_format'] === 'S'
                        && ! isset($requests['agent_performance']['params']['campaign_id'])
                        && $requests['call_totals']['function'] === 'call_status_stats'
                        && $requests['call_totals']['params']['campaigns'] === 'campaign-a';
                }),
                true,
                $httpOptions,
                Mockery::type(VicidialServer::class),
            )
            ->andReturn([
                'logged_agents' => OperationResult::success(['rows' => []]),
                'agent_performance' => OperationResult::success(['rows' => []]),
                'call_totals' => OperationResult::success(['rows' => []]),
            ]);

        $service = new ReportingService($nonAgentApi, $this->scopeResolver());
        $result = $service->supervisorSnapshot($user, 'campaign-a', '2026-08-26', $httpOptions);

        $this->assertCount(3, $result);
    }

    public function test_user_group_status_forwards_bounded_http_options(): void
    {
        $user = User::factory()->make();
        $nonAgentApi = Mockery::mock(VicidialNonAgentApiService::class);
        $httpOptions = ['connect_timeout' => 1, 'timeout' => 3, 'retry_times' => 0];

        $nonAgentApi->shouldReceive('executeOnServer')
            ->once()
            ->with(
                $user,
                Mockery::type(VicidialServer::class),
                'campaign-a',
                'user_group_status',
                [
                    'user_groups' => 'SALES|RETENTION',
                    'stage' => 'pipe',
                    'header' => 'YES',
                ],
                true,
                $httpOptions,
            )
            ->andReturn(OperationResult::success(['rows' => []]));

        $service = new ReportingService($nonAgentApi, $this->scopeResolver());
        $result = $service->userGroupStatus($user, 'campaign-a', 'SALES|RETENTION', $httpOptions);

        $this->assertTrue($result->success);
    }

    private function scopeResolver(): CrmCampaignVicidialScopeResolver
    {
        $resolver = Mockery::mock(CrmCampaignVicidialScopeResolver::class);
        $resolver->shouldReceive('resolve')->andReturnUsing(function (string $campaignCode): VicidialCampaignScope {
            $campaign = new Campaign(['code' => $campaignCode, 'name' => $campaignCode]);
            $campaign->id = 1;
            $campaign->exists = true;
            $server = new VicidialServer(['campaign_code' => $campaignCode, 'server_name' => 'Test Server']);
            $server->id = 1;
            $server->exists = true;
            $mapping = new CampaignVicidialMapping([
                'vicidial_campaign_code' => $campaignCode === 'mbsales' ? 'TESTCAMP' : 'campaign-a',
                'is_enabled' => true,
                'status' => CampaignVicidialMapping::STATUS_ACTIVE,
            ]);

            return new VicidialCampaignScope($campaign, $server, collect([$mapping]));
        });

        return $resolver;
    }
}
