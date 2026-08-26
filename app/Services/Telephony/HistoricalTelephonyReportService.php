<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;
use Illuminate\Support\Carbon;

class HistoricalTelephonyReportService
{
    public function __construct(
        protected ReportingService $reportingService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(User $user, string $crmCampaign, array $filters): array
    {
        $period = $this->period($filters);
        $params = [
            'campaigns' => $filters['campaigns'] ?? '---ALL---',
            'ingroups' => $filters['ingroups'] ?? null,
            'disposition_scope' => $filters['disposition_scope'] ?? 'all',
            'query_date' => $period['start']->format('Y-m-d'),
            'end_date' => $period['end']->format('Y-m-d'),
            'datetime_start' => $period['start']->format('Y-m-d').'+00:00:00',
            'datetime_end' => $period['end']->format('Y-m-d').'+23:59:59',
        ];
        $current = $this->reportingService->historicalSnapshot(
            $user,
            $crmCampaign,
            $params,
            ['connect_timeout' => 3, 'timeout' => 10, 'retry_times' => 1],
        );
        $callStatus = $this->parseCallStatus($current['call_status'] ?? null);
        $agents = $this->parseAgentStats($current['agent_stats'] ?? null);
        $dispositions = $this->parseDispositions(
            $current['call_dispo'] ?? null,
            (string) ($filters['disposition_scope'] ?? 'all'),
        );
        $summary = $this->summary($callStatus, $agents, $dispositions);

        $comparisonMode = (string) ($filters['comparison'] ?? 'none');
        $comparison = $this->comparison(
            $user,
            $crmCampaign,
            $params,
            $period,
            $comparisonMode,
            $summary,
        );
        $sourceStatus = $this->sourceStatus($current);

        return [
            'filters' => [
                'crm_campaign' => $crmCampaign,
                'campaigns' => $params['campaigns'],
                'ingroups' => $params['ingroups'],
                'query_date' => $period['start']->format('Y-m-d'),
                'end_date' => $period['end']->format('Y-m-d'),
                'disposition_scope' => $filters['disposition_scope'] ?? 'all',
                'comparison' => $comparisonMode,
            ],
            'availability' => $sourceStatus,
            'summary' => $summary,
            'comparison' => $comparison,
            'call_volume' => $callStatus['call_volume'],
            'status_totals' => $callStatus['status_totals'] ?? [],
            'campaigns' => $callStatus['campaigns'],
            'dispositions' => $dispositions['pareto'],
            'disposition_rows' => $dispositions['rows'],
            'funnel' => $this->funnel($summary['total_calls'], $callStatus['answered_calls'], $dispositions['code_totals']),
            'agents' => $agents['rows'],
            'agent_summary' => $agents['summary'],
            'time_distribution' => $agents['time_distribution'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{start: Carbon, end: Carbon}
     */
    protected function period(array $filters): array
    {
        $start = Carbon::parse((string) ($filters['query_date'] ?? now()->format('Y-m-d')))->startOfDay();
        $end = Carbon::parse((string) ($filters['end_date'] ?? $start->format('Y-m-d')))->startOfDay();
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array{start: Carbon, end: Carbon}  $period
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function comparison(
        User $user,
        string $crmCampaign,
        array $params,
        array $period,
        string $mode,
        array $summary,
    ): array {
        if ($mode === 'none') {
            return ['enabled' => false, 'mode' => 'none', 'period' => null, 'metrics' => []];
        }

        $days = $period['start']->diffInDays($period['end']) + 1;
        $previousStart = match ($mode) {
            'previous_day' => $period['start']->copy()->subDay(),
            'previous_week' => $period['start']->copy()->subWeek(),
            'previous_month' => $period['start']->copy()->subMonth(),
            default => $period['start']->copy()->subDays($days),
        };
        $previousEnd = match ($mode) {
            'previous_day' => $period['end']->copy()->subDay(),
            'previous_week' => $period['end']->copy()->subWeek(),
            'previous_month' => $period['end']->copy()->subMonth(),
            default => $period['end']->copy()->subDays($days),
        };
        $previousParams = [
            ...$params,
            'query_date' => $previousStart->format('Y-m-d'),
            'end_date' => $previousEnd->format('Y-m-d'),
            'datetime_start' => $previousStart->format('Y-m-d').'+00:00:00',
            'datetime_end' => $previousEnd->format('Y-m-d').'+23:59:59',
        ];
        $previous = $this->reportingService->historicalSnapshot(
            $user,
            $crmCampaign,
            $previousParams,
            ['connect_timeout' => 3, 'timeout' => 10, 'retry_times' => 1],
        );
        $previousStatus = $this->parseCallStatus($previous['call_status'] ?? null);
        $previousAgents = $this->parseAgentStats($previous['agent_stats'] ?? null);
        $previousDispositions = $this->parseDispositions(
            $previous['call_dispo'] ?? null,
            (string) ($params['disposition_scope'] ?? 'all'),
        );
        $previousSummary = $this->summary($previousStatus, $previousAgents, $previousDispositions);
        $metrics = [];
        foreach ([
            'total_calls' => 'count',
            'answered_calls' => 'count',
            'answer_rate' => 'rate',
            'contact_rate' => 'rate',
            'average_talk_time_seconds' => 'duration',
            'agents_with_activity' => 'count',
            'calls_per_agent' => 'rate',
        ] as $key => $unit) {
            $currentValue = $summary[$key];
            $previousValue = $previousSummary[$key];
            $metrics[$key] = [
                'current' => $currentValue,
                'previous' => $previousValue,
                'change' => $this->difference($currentValue, $previousValue, $unit),
                'unit' => $unit,
            ];
        }

        return [
            'enabled' => true,
            'mode' => $mode,
            'period' => [
                'start' => $previousStart->format('Y-m-d'),
                'end' => $previousEnd->format('Y-m-d'),
            ],
            'availability' => $this->sourceStatus($previous),
            'metrics' => $metrics,
        ];
    }

    private function difference(mixed $current, mixed $previous, string $unit): ?float
    {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }
        if ($unit === 'rate') {
            return round((float) $current - (float) $previous, 2);
        }
        if ($unit === 'duration') {
            return round((float) $current - (float) $previous, 1);
        }
        if ((float) $previous === 0.0) {
            return null;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 2);
    }

    /**
     * @param  array<string, OperationResult>  $results
     * @return array<string, mixed>
     */
    protected function sourceStatus(array $results): array
    {
        $known = array_values(array_filter($results, static fn (mixed $result): bool => $result instanceof OperationResult));
        $successful = array_values(array_filter($known, static fn (OperationResult $result): bool => $result->success));
        $failed = count($known) - count($successful);
        $status = count($successful) === 0 ? 'unavailable' : ($failed > 0 ? 'degraded' : 'live');

        return [
            'status' => $status,
            'available_sections' => count($successful),
            'failed_sections' => $failed,
            'message' => $status === 'live' ? null : 'Some historical VICIdial report data is unavailable. Retry the report or verify the campaign server permissions.',
        ];
    }

    /**
     * @param  array<string, mixed>  $callStatus
     * @param  array<string, mixed>  $agents
     * @param  array<string, mixed>  $dispositions
     * @return array<string, int|float|null>
     */
    protected function summary(array $callStatus, array $agents, array $dispositions): array
    {
        $totalCalls = (int) $callStatus['total_calls'];
        $answeredCalls = (int) $callStatus['answered_calls'];
        $contacted = $dispositions['group_totals']['contacted'] ?? null;
        $averageTalk = $agents['summary']['average_talk_time_seconds'];
        $agentCount = (int) $agents['summary']['agents_with_activity'];

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 2) : 0,
            'contact_rate' => is_numeric($contacted) && $totalCalls > 0
                ? round(((float) $contacted / $totalCalls) * 100, 2)
                : null,
            'average_talk_time_seconds' => $averageTalk,
            'agents_with_activity' => $agentCount,
            'calls_per_agent' => $agentCount > 0 ? round($totalCalls / $agentCount, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseCallStatus(?OperationResult $result): array
    {
        $empty = [
            'total_calls' => 0,
            'answered_calls' => 0,
            'campaigns' => [],
            'call_volume' => ['labels' => [], 'values' => [], 'grouping' => 'hourly'],
        ];
        if ($result === null || ! $result->success) {
            return $empty;
        }
        $campaignMap = [];
        $hourMap = [];
        $statusMap = [];
        foreach ($this->rows($result) as $row) {
            $label = trim((string) ($row[0] ?? 'Unknown'));
            if (strtoupper($label) === 'TOTAL') {
                continue;
            }
            $total = $this->number($row[1] ?? 0);
            $answered = $this->number($row[2] ?? 0);
            if (! isset($campaignMap[$label])) {
                $campaignMap[$label] = ['campaign' => $label, 'total_calls' => 0, 'answered_calls' => 0, 'answer_rate' => 0];
            }
            $campaignMap[$label]['total_calls'] += $total;
            $campaignMap[$label]['answered_calls'] += $answered;
            foreach ($this->pairs($row[3] ?? '', ',', '-') as $pair) {
                $hour = str_pad((string) ((int) $pair['label']), 2, '0', STR_PAD_LEFT);
                $hourMap[$hour] = ($hourMap[$hour] ?? 0) + $pair['value'];
            }
            foreach ($this->pairs($row[4] ?? '', ',', '-') as $pair) {
                $statusMap[$pair['label']] = ($statusMap[$pair['label']] ?? 0) + $pair['value'];
            }
        }
        foreach ($campaignMap as &$campaign) {
            $campaign['answer_rate'] = $campaign['total_calls'] > 0
                ? round(($campaign['answered_calls'] / $campaign['total_calls']) * 100, 2)
                : 0;
        }
        unset($campaign);
        ksort($hourMap);
        arsort($statusMap);

        return [
            'total_calls' => array_sum(array_column($campaignMap, 'total_calls')),
            'answered_calls' => array_sum(array_column($campaignMap, 'answered_calls')),
            'campaigns' => array_values($campaignMap),
            'status_totals' => $statusMap,
            'call_volume' => [
                'labels' => array_keys($hourMap),
                'values' => array_values($hourMap),
                'grouping' => 'hourly',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseAgentStats(?OperationResult $result): array
    {
        $empty = [
            'rows' => [],
            'summary' => [
                'agents_with_activity' => 0,
                'total_calls' => 0,
                'average_talk_time_seconds' => null,
                'total_talk_time_seconds' => null,
                'total_pause_time_seconds' => null,
            ],
            'time_distribution' => ['talk_seconds' => null, 'pause_seconds' => null, 'ready_seconds' => null, 'other_seconds' => null],
        ];
        if ($result === null || ! $result->success) {
            return $empty;
        }
        $rows = $this->rows($result);
        if (count($rows) < 2) {
            return $empty;
        }
        $headers = array_map(fn (mixed $header): string => $this->key($header), $rows[0]);
        $agents = [];
        $totalTalk = 0;
        $totalPause = 0;
        $hasTalk = false;
        $hasPause = false;
        foreach (array_slice($rows, 1) as $row) {
            $data = [];
            foreach ($headers as $index => $header) {
                $data[$header] = $row[$index] ?? '';
            }
            $user = trim((string) ($data['user'] ?? $data['agent_user'] ?? $data['username'] ?? ''));
            if ($user === '') {
                continue;
            }
            $calls = $this->number($data['calls'] ?? $data['calls_today'] ?? $data['total_calls'] ?? 0);
            $talk = $this->seconds($data['total_talk_time'] ?? $data['talk_time'] ?? null);
            $avgTalk = $this->seconds($data['avg_talk_time'] ?? $data['average_talk_time'] ?? null);
            $talk = $talk ?? ($avgTalk !== null ? $avgTalk * $calls : null);
            $pause = $this->seconds($data['pause_time'] ?? $data['total_pause_time'] ?? null);
            if (! isset($agents[$user])) {
                $agents[$user] = [
                    'user' => $user,
                    'full_name' => $data['full_name'] ?? $user,
                    'user_group' => $data['user_group'] ?? $data['group'] ?? '',
                    'calls' => 0,
                    'answered' => 0,
                    'contacted' => null,
                    'total_talk_time_seconds' => 0,
                    'total_wait_time_seconds' => 0,
                    'pause_time_seconds' => 0,
                    'pause_pct' => null,
                ];
            }
            $agents[$user]['calls'] += $calls;
            $agents[$user]['answered'] += $this->number($data['answered'] ?? $data['answered_calls'] ?? 0);
            $agents[$user]['total_talk_time_seconds'] += $talk ?? 0;
            $agents[$user]['total_wait_time_seconds'] += $this->seconds($data['total_wait_time'] ?? $data['wait_time'] ?? null) ?? 0;
            $agents[$user]['pause_time_seconds'] += $pause ?? 0;
            if ($talk !== null) {
                $hasTalk = true;
                $totalTalk += $talk;
            }
            if ($pause !== null) {
                $hasPause = true;
                $totalPause += $pause;
            }
            $agents[$user]['pause_pct'] = $this->percent($data['pause_pct'] ?? null);
        }
        foreach ($agents as &$agent) {
            $agent['answer_rate'] = $agent['calls'] > 0 ? round(($agent['answered'] / $agent['calls']) * 100, 2) : 0;
            $agent['avg_talk_time_seconds'] = $agent['calls'] > 0 && $agent['total_talk_time_seconds'] > 0
                ? round($agent['total_talk_time_seconds'] / $agent['calls'], 1)
                : null;
        }
        unset($agent);
        usort($agents, static fn (array $left, array $right): int => $right['calls'] <=> $left['calls']);
        $totalCalls = array_sum(array_column($agents, 'calls'));

        return [
            'rows' => array_values($agents),
            'summary' => [
                'agents_with_activity' => count($agents),
                'total_calls' => $totalCalls,
                'average_talk_time_seconds' => $totalCalls > 0 && $hasTalk ? round($totalTalk / $totalCalls, 1) : null,
                'total_talk_time_seconds' => $hasTalk ? $totalTalk : null,
                'total_pause_time_seconds' => $hasPause ? $totalPause : null,
            ],
            'time_distribution' => [
                'talk_seconds' => $hasTalk ? $totalTalk : null,
                'pause_seconds' => $hasPause ? $totalPause : null,
                'ready_seconds' => null,
                'other_seconds' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseDispositions(?OperationResult $result, string $scope): array
    {
        $empty = ['rows' => [], 'pareto' => ['labels' => [], 'values' => [], 'percentages' => []], 'code_totals' => [], 'group_totals' => []];
        if ($result === null || ! $result->success) {
            return $empty;
        }
        $rows = $this->rows($result);
        if (count($rows) < 2) {
            return $empty;
        }
        $labels = array_map('strval', array_slice($rows[0], 2));
        $systemCodes = array_flip(array_map([$this, 'normalizeCode'], config('vicidial.report_system_disposition_codes', [])));
        $codeTotals = [];
        $reportRows = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            $campaign = trim((string) ($row[0] ?? 'Unknown'));
            if (strtoupper($campaign) === 'TOTAL') {
                continue;
            }
            $metrics = [];
            foreach ($labels as $labelIndex => $label) {
                $code = $this->normalizeCode($label);
                $isSystem = isset($systemCodes[$code]);
                if (($scope === 'exclude_system' && $isSystem) || ($scope === 'system_only' && ! $isSystem)) {
                    continue;
                }
                $value = $this->displayNumber($row[$labelIndex + 2] ?? 0);
                $codeTotals[$label] = ($codeTotals[$label] ?? 0) + $value;
                $metrics[] = ['label' => $label, 'value' => $value, 'percentage' => null, 'system' => $isSystem];
            }
            if ($metrics === []) {
                continue;
            }
            usort($metrics, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
            $reportRows[] = [
                'campaign' => $campaign,
                'total_calls' => array_sum(array_column($metrics, 'value')),
                'top_disposition' => $metrics[0]['label'] ?? '—',
                'metrics' => $metrics,
            ];
        }
        arsort($codeTotals);
        $top = array_slice($codeTotals, 0, 10, true);
        $other = array_sum(array_slice($codeTotals, 10, null, true));
        if ($other > 0) {
            $top['Other'] = $other;
        }
        $total = array_sum($codeTotals);
        $pareto = ['labels' => array_keys($top), 'values' => array_values($top), 'percentages' => array_map(
            fn (int|float $value): float => $total > 0 ? round(($value / $total) * 100, 2) : 0,
            array_values($top),
        )];
        $groups = config('vicidial.report_disposition_groups', []);
        $groupTotals = [];
        foreach (['contacted', 'qualified', 'successful'] as $group) {
            $codes = array_map([$this, 'normalizeCode'], $groups[$group] ?? []);
            if ($codes === []) {
                continue;
            }
            $groupTotals[$group] = 0;
            foreach ($codeTotals as $code => $value) {
                if (in_array($this->normalizeCode($code), $codes, true)) {
                    $groupTotals[$group] += $value;
                }
            }
        }

        return ['rows' => $reportRows, 'pareto' => $pareto, 'code_totals' => $codeTotals, 'group_totals' => $groupTotals];
    }

    /**
     * @param  array<string, int|float>  $codeTotals
     * @return array<int, array<string, int|float|string>>
     */
    protected function funnel(int $totalCalls, int $answeredCalls, array $codeTotals): array
    {
        $groups = config('vicidial.report_disposition_groups', []);
        $configuredGroups = array_filter(
            ['contacted', 'qualified', 'successful'],
            static fn (string $group): bool => ($groups[$group] ?? []) !== [],
        );
        if ($configuredGroups === []) {
            return [];
        }
        $stages = [['key' => 'total_dialed', 'label' => 'Total Dialed', 'value' => $totalCalls]];
        $values = ['answered' => $answeredCalls];
        foreach (['contacted', 'qualified', 'successful'] as $group) {
            $codes = array_map([$this, 'normalizeCode'], $groups[$group] ?? []);
            if ($codes === []) {
                continue;
            }
            $values[$group] = 0;
            foreach ($codeTotals as $code => $value) {
                if (in_array($this->normalizeCode($code), $codes, true)) {
                    $values[$group] += $value;
                }
            }
        }
        foreach ($values as $key => $value) {
            $stages[] = ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)), 'value' => $value];
        }

        return $stages;
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function rows(OperationResult $result): array
    {
        return array_values(array_filter((array) ($result->data['rows'] ?? []), 'is_array'));
    }

    protected function number(mixed $value): int
    {
        $value = preg_replace('/[^0-9.-]/', '', (string) $value);

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    protected function displayNumber(mixed $value): int
    {
        return $this->number($value);
    }

    protected function percent(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        preg_match('/-?[0-9.]+/', $value, $matches);

        return isset($matches[0]) ? (float) $matches[0] : null;
    }

    protected function seconds(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0, (int) round((float) $value));
        }
        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2})$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (int) $matches[3];
        }
        if (preg_match('/^(\d+):(\d{1,2})$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    protected function pairs(mixed $value, string $entrySeparator, string $pairSeparator): array
    {
        $pairs = [];
        foreach (explode($entrySeparator, (string) $value) as $entry) {
            $parts = explode($pairSeparator, trim($entry), 2);
            if (count($parts) !== 2 || trim($parts[0]) === '') {
                continue;
            }
            $pairs[] = ['label' => trim($parts[0]), 'value' => $this->number($parts[1])];
        }

        return $pairs;
    }

    protected function key(mixed $value): string
    {
        return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $value), '_'));
    }

    protected function normalizeCode(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
