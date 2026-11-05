<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RealtimeTelephonyReportService
{
    public function __construct(
        protected SupervisorOperationalService $operationalService,
    ) {}

    /**
     * Build a Live or Today report from one normalized Supervisor snapshot and
     * campaign-scoped CRM events. Remote failures remain visible in sources.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Request $request, string $mode = 'live'): array
    {
        $mode = in_array($mode, ['live', 'today'], true) ? $mode : 'live';
        $snapshot = $this->operationalService->snapshot($request);
        $campaign = (string) ($snapshot['campaign']['code'] ?? $snapshot['routing']['campaign_code'] ?? '');
        $vicidialCampaigns = array_values(array_filter(
            (array) ($snapshot['routing']['live_vicidial_campaigns'] ?? []),
            static fn (mixed $code): bool => trim((string) $code) !== '',
        ));
        $now = now();
        $windowMinutes = max(1, (int) config('vicidial.supervisor.rolling_window_minutes', 15));
        $rolling = is_array($snapshot['stats']['rolling'] ?? null)
            ? $snapshot['stats']['rolling']
            : $this->rollingMetrics($vicidialCampaigns, $now->copy()->subMinutes($windowMinutes), $now, $windowMinutes);
        $today = $this->todayMetrics($vicidialCampaigns, $now, $snapshot);
        $snapshotHealth = (string) ($snapshot['health'] ?? 'offline');
        $availabilityStatus = match ($snapshotHealth) {
            'live' => 'live',
            'degraded' => 'degraded',
            default => 'unavailable',
        };

        return [
            'mode' => $mode,
            'time_scope' => $mode === 'today'
                ? ['label' => 'Today', 'start' => $now->copy()->startOfDay()->toIso8601String(), 'end' => $now->toIso8601String()]
                : ['label' => 'Last '.$windowMinutes.' minutes', 'start' => $now->copy()->subMinutes($windowMinutes)->toIso8601String(), 'end' => $now->toIso8601String()],
            'campaign' => $snapshot['campaign'] ?? null,
            'server' => $snapshot['server'] ?? null,
            'snapshot' => $snapshot,
            'metrics' => [
                'active_agents' => $snapshot['stats']['agentsOnline'] ?? null,
                'available_agents' => $snapshot['stats']['agentsAvailable'] ?? null,
                'paused_agents' => $snapshot['stats']['agentsPaused'] ?? null,
                'live_calls' => $snapshot['stats']['callsActive'] ?? null,
                'calls_waiting' => $snapshot['stats']['callsWaiting'] ?? null,
                'oldest_wait_seconds' => $snapshot['stats']['oldestWaitSeconds'] ?? null,
                'average_wait_seconds' => $snapshot['stats']['avgWaitTime'] ?? null,
            ],
            'rolling' => $rolling,
            'today' => $today,
            'agents' => $snapshot['agents'] ?? [],
            'active_calls' => $snapshot['active_calls'] ?? [],
            'queue' => $snapshot['stats']['queue'] ?? null,
            'sources' => $snapshot['sources'] ?? [],
            'freshness' => $snapshot['freshness'] ?? [
                'status' => $availabilityStatus,
                'last_success_at' => null,
                'stale_after_seconds' => null,
            ],
            'availability' => [
                'status' => $availabilityStatus,
                'message' => $snapshot['routing']['message'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rollingMetrics(array $vicidialCampaigns, Carbon $from, Carbon $until, int $windowMinutes): array
    {
        if ($vicidialCampaigns === []) {
            return $this->emptyRolling($windowMinutes, false);
        }

        $metrics = [
            'attempted' => 0,
            'answered' => 0,
            'abandoned' => 0,
            'wait_total' => 0,
            'wait_count' => 0,
            'talk_total' => 0,
            'talk_count' => 0,
            'dispositions' => [],
        ];
        $chunkSize = max(1, (int) config('vicidial.supervisor.rolling_chunk_size', 500));

        CallSession::query()
            ->whereIn('campaign_code', $vicidialCampaigns)
            ->where(function ($query) use ($from, $until): void {
                $query->whereBetween('dialed_at', [$from, $until])
                    ->orWhereBetween('answered_at', [$from, $until])
                    ->orWhereBetween('ended_at', [$from, $until])
                    ->orWhereBetween('disposition_at', [$from, $until]);
            })
            ->select([
                'id',
                'status',
                'dialed_at',
                'answered_at',
                'ended_at',
                'call_duration_seconds',
                'disposition_code',
                'disposition_at',
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $calls) use ($from, $until, &$metrics): void {
                foreach ($calls as $call) {
                    if (! $this->callHasEventInWindow($call, $from, $until)) {
                        continue;
                    }

                    $metrics['attempted']++;
                    if ($call->answered_at !== null) {
                        $metrics['answered']++;
                        if ($call->dialed_at !== null) {
                            $metrics['wait_total'] += max(0, (int) $call->dialed_at->diffInSeconds($call->answered_at, false));
                            $metrics['wait_count']++;
                        }
                        if ($call->call_duration_seconds !== null) {
                            $metrics['talk_total'] += max(0, (int) $call->call_duration_seconds);
                            $metrics['talk_count']++;
                        }
                    }
                    if ($call->status === CallSession::STATUS_ABANDONED) {
                        $metrics['abandoned']++;
                    }
                    $code = trim((string) $call->disposition_code);
                    if ($code !== '') {
                        $metrics['dispositions'][$code] = ($metrics['dispositions'][$code] ?? 0) + 1;
                    }
                }
            }, 'id');

        $dispositions = collect($metrics['dispositions'])->sortDesc()->all();
        $attempted = $metrics['attempted'];

        return [
            'window_minutes' => $windowMinutes,
            'label' => 'Last '.$windowMinutes.' minutes',
            'source' => 'crm',
            'available' => true,
            'calls_initiated' => $attempted,
            'answered' => $metrics['answered'],
            'abandoned' => $metrics['abandoned'],
            'answer_rate' => $attempted > 0 ? round(($metrics['answered'] / $attempted) * 100, 2) : 0,
            'average_wait_seconds' => $metrics['wait_count'] > 0 ? round($metrics['wait_total'] / $metrics['wait_count'], 1) : null,
            'average_talk_seconds' => $metrics['talk_count'] > 0 ? round($metrics['talk_total'] / $metrics['talk_count'], 1) : null,
            'dispositions' => $dispositions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function todayMetrics(array $vicidialCampaigns, Carbon $now, array $snapshot): array
    {
        $stats = is_array($snapshot['stats'] ?? null) ? $snapshot['stats'] : [];
        $remoteTotal = $stats['todayTotal'] ?? null;
        $remoteAnswered = $stats['callsAnswered'] ?? null;
        $remoteAnswerRate = $stats['answerRate'] ?? null;

        if (($stats['callSource'] ?? null) === 'vicidial'
            && is_numeric($remoteTotal)
            && is_numeric($remoteAnswered)) {
            $totalCalls = max(0, (int) $remoteTotal);
            $answered = max(0, (int) $remoteAnswered);

            return [
                'label' => 'Midnight → now',
                'day_key' => $now->toDateString(),
                'start' => $now->copy()->startOfDay()->toIso8601String(),
                'end' => $now->toIso8601String(),
                'total_calls' => $totalCalls,
                'answered' => $answered,
                'answer_rate' => is_numeric($remoteAnswerRate)
                    ? round((float) $remoteAnswerRate, 1)
                    : ($totalCalls > 0 ? round(($answered / $totalCalls) * 100, 1) : 0),
                'source' => 'vicidial',
                'available' => true,
                'dispositions' => [],
            ];
        }

        if ($vicidialCampaigns === []) {
            return [
                'label' => 'Midnight → now',
                'day_key' => $now->toDateString(),
                'start' => $now->copy()->startOfDay()->toIso8601String(),
                'end' => $now->toIso8601String(),
                'total_calls' => null,
                'answered' => null,
                'answer_rate' => null,
                'source' => 'unavailable',
                'available' => false,
                'dispositions' => [],
            ];
        }

        $baseQuery = DB::table('call_sessions')
            ->whereIn('campaign_code', $vicidialCampaigns)
            ->whereBetween('dialed_at', [$now->copy()->startOfDay(), $now]);
        $aggregate = (clone $baseQuery)
            ->selectRaw('COUNT(*) as attempted, SUM(CASE WHEN answered_at IS NOT NULL THEN 1 ELSE 0 END) as answered')
            ->first();
        $dispositionQuery = (clone $baseQuery)->whereNotNull('disposition_code')->where('disposition_code', '<>', '');
        if (DB::connection()->getDriverName() === 'mysql') {
            $dispositionQuery->selectRaw('BINARY disposition_code as disposition_code, COUNT(*) as total')
                ->groupByRaw('BINARY disposition_code');
        } else {
            $dispositionQuery->selectRaw('disposition_code, COUNT(*) as total')->groupBy('disposition_code');
        }
        $dispositions = [];
        foreach ($dispositionQuery->get() as $row) {
            $dispositions[(string) $row->disposition_code] = (int) $row->total;
        }
        $dispositions = collect($dispositions)->sortDesc()->all();
        $attempted = (int) ($aggregate->attempted ?? 0);
        $answered = (int) ($aggregate->answered ?? 0);

        return [
            'label' => 'Midnight → now',
            'day_key' => $now->toDateString(),
            'start' => $now->copy()->startOfDay()->toIso8601String(),
            'end' => $now->toIso8601String(),
            'total_calls' => $attempted,
            'answered' => $answered,
            'answer_rate' => $attempted > 0 ? round(($answered / $attempted) * 100, 2) : 0,
            'source' => 'crm',
            'available' => true,
            'dispositions' => $dispositions,
        ];
    }

    protected function callHasEventInWindow(CallSession $call, Carbon $from, Carbon $until): bool
    {
        $eventTimes = [$call->dialed_at, $call->answered_at, $call->ended_at, $call->disposition_at];

        foreach ($eventTimes as $eventTime) {
            if ($eventTime !== null && $eventTime->betweenIncluded($from, $until)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRolling(int $windowMinutes, bool $available): array
    {
        return [
            'window_minutes' => $windowMinutes,
            'label' => 'Last '.$windowMinutes.' minutes',
            'source' => $available ? 'crm' : 'unavailable',
            'available' => $available,
            'calls_initiated' => null,
            'answered' => null,
            'abandoned' => null,
            'answer_rate' => null,
            'average_wait_seconds' => null,
            'average_talk_seconds' => null,
            'dispositions' => [],
        ];
    }
}
