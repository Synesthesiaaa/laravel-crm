<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Telephony\ReportingService;
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
            ->once()
            ->with(
                $user,
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
            )
            ->andReturn(OperationResult::success(['rows' => []]));

        $service = new ReportingService($nonAgentApi);

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

        $nonAgentApi->shouldReceive('execute')
            ->once()
            ->with(
                $user,
                'campaign-a',
                'call_status_stats',
                [
                    'campaigns' => '---ALL---',
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

        $service = new ReportingService($nonAgentApi);

        $result = $service->callStatusStats(
            $user,
            'campaign-a',
            ['campaigns' => '---ALL---', 'query_date' => '2026-08-26'],
            ['connect_timeout' => 1, 'timeout' => 3, 'retry_times' => 0],
        );

        $this->assertTrue($result->success);
    }
}
