<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\User;
use App\Services\Telephony\RealtimeTelephonyReportService;
use App\Services\Telephony\SupervisorOperationalService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RealtimeTelephonyReportAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_fallback_aggregates_calls_without_hydrating_models(): void
    {
        $user = User::factory()->create();
        $now = now();
        foreach (range(1, 120) as $index) {
            $dialedAt = $now->copy()->subSeconds($index + 1);
            DB::table('call_sessions')->insert([
                'user_id' => $user->id,
                'campaign_code' => 'VICI_A',
                'lead_id' => $index,
                'phone_number' => '639120000000',
                'status' => $index % 5 === 0 ? CallSession::STATUS_ABANDONED : CallSession::STATUS_COMPLETED,
                'linkedid' => 'synthetic-'.$index,
                'channel' => null,
                'vicidial_lead_id' => null,
                'dialed_at' => $dialedAt,
                'ringing_at' => null,
                'answered_at' => $index % 2 === 0 ? $dialedAt->copy()->addSeconds(5) : null,
                'ended_at' => $dialedAt->copy()->addSeconds(30),
                'disposition_code' => $index % 3 === 0 ? 'SALE' : 'NA',
                'disposition_label' => null,
                'disposition_at' => $dialedAt->copy()->addSeconds(30),
                'disposition_remarks' => null,
                'call_duration_seconds' => $index % 2 === 0 ? 25 : null,
                'end_reason' => null,
                'metadata' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
                'vicidial_call_id' => null,
            ]);
        }

        $snapshot = [
            'campaign' => ['code' => 'mbsales'],
            'server' => null,
            'routing' => ['live_vicidial_campaigns' => ['VICI_A']],
            'stats' => [
                'rolling' => [],
                'callSource' => 'crm',
                'todayTotal' => null,
                'callsAnswered' => null,
            ],
            'health' => 'offline',
            'agents' => [],
            'active_calls' => [],
            'sources' => [],
        ];
        $operational = $this->mock(SupervisorOperationalService::class);
        $operational->shouldReceive('snapshot')->once()->andReturn($snapshot);
        $retrieved = 0;
        CallSession::retrieved(function () use (&$retrieved): void {
            $retrieved++;
        });

        $report = (new RealtimeTelephonyReportService($operational))->dashboard(Request::create('/'), 'today');

        $this->assertSame(120, $report['today']['total_calls']);
        $this->assertSame(60, $report['today']['answered']);
        $this->assertSame(['NA' => 80, 'SALE' => 40], $report['today']['dispositions']);
        $this->assertSame(0, $retrieved);
    }

    public function test_rolling_metrics_keep_exact_event_window_results_across_bounded_chunks(): void
    {
        config(['vicidial.supervisor.rolling_chunk_size' => 2]);
        $user = User::factory()->create();
        $now = Carbon::parse('2026-10-26 12:00:00');
        $from = $now->copy()->subMinutes(15);
        $this->insertCall($user->id, 'one', CallSession::STATUS_COMPLETED, $now->copy()->subMinutes(4), $now->copy()->subMinutes(3), $now->copy()->subMinutes(2), $now->copy()->subMinutes(2), 60, 'BUSY');
        $this->insertCall($user->id, 'two', CallSession::STATUS_ABANDONED, $now->copy()->subMinutes(5), null, $now->copy()->subMinutes(4), $now->copy()->subMinutes(4), null, 'NOANSWER');
        $this->insertCall($user->id, 'three', CallSession::STATUS_COMPLETED, $now->copy()->subMinutes(16), $now->copy()->subMinutes(14), $now->copy()->subMinutes(13), $now->copy()->subMinutes(13), 30, 'SALE');
        $this->insertCall($user->id, 'four', CallSession::STATUS_COMPLETED, $now->copy()->subMinutes(20), null, $now->copy()->subMinutes(18), $now->copy()->subMinutes(18), null, 'OLD');
        $this->insertCall($user->id, 'five', CallSession::STATUS_COMPLETED, $from, $now->copy()->subMinutes(14), $now->copy()->subMinutes(13), $now->copy()->subMinutes(13), -5, 'BUSY');
        $this->insertCall($user->id, 'six', CallSession::STATUS_COMPLETED, $now->copy()->subMinutes(30), null, $now->copy()->subMinutes(29), $now, null, 'UPDATED');
        $queryLimits = [];
        DB::listen(function (QueryExecuted $query) use (&$queryLimits): void {
            if (str_contains(strtolower($query->sql), 'from "call_sessions"') || str_contains(strtolower($query->sql), 'from `call_sessions`')) {
                $queryLimits[] = $query->sql;
            }
        });
        $service = new class($this->mock(SupervisorOperationalService::class)) extends RealtimeTelephonyReportService
        {
            public function roll(array $campaigns, Carbon $from, Carbon $until): array
            {
                return $this->rollingMetrics($campaigns, $from, $until, 15);
            }
        };

        $result = $service->roll(['VICI_A'], $from, $now);

        $this->assertSame(5, $result['calls_initiated']);
        $this->assertSame(3, $result['answered']);
        $this->assertSame(1, $result['abandoned']);
        $this->assertSame(60.0, $result['answer_rate']);
        $this->assertSame(80.0, $result['average_wait_seconds']);
        $this->assertSame(30.0, $result['average_talk_seconds']);
        $this->assertSame(['BUSY' => 2, 'NOANSWER' => 1, 'SALE' => 1, 'UPDATED' => 1], $result['dispositions']);
        $this->assertGreaterThanOrEqual(3, count($queryLimits));
        foreach ($queryLimits as $sql) {
            $this->assertStringContainsString('limit 2', strtolower($sql));
        }
    }

    private function insertCall(int $userId, string $identity, string $status, ?Carbon $dialed, ?Carbon $answered, ?Carbon $ended, ?Carbon $dispositionAt, ?int $duration, ?string $disposition): void
    {
        CallSession::query()->create([
            'user_id' => $userId,
            'campaign_code' => 'VICI_A',
            'phone_number' => '639120000000',
            'status' => $status,
            'linkedid' => 'rolling-'.$identity,
            'dialed_at' => $dialed,
            'answered_at' => $answered,
            'ended_at' => $ended,
            'disposition_at' => $dispositionAt,
            'call_duration_seconds' => $duration,
            'disposition_code' => $disposition,
            'metadata' => [],
        ]);
    }
}
