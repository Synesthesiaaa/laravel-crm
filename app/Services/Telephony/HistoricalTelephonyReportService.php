<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;
use Illuminate\Support\Carbon;

class HistoricalTelephonyReportService
{
    public function __construct(
        protected ReportingService $reportingService,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(User $user, string $crmCampaign, array $filters): array
    {
        $period = $this->period($filters);
        $scope = $this->scopeResolver->resolve($crmCampaign);
        $allowedCampaignCodes = $scope->historicalCampaignCodes();
        if ($scope->server === null || $allowedCampaignCodes === []) {
            return $this->unavailableDashboard($crmCampaign, $period, $filters, $scope);
        }
        $selectedCampaignCodes = $scope->narrowCampaignCodes(
            isset($filters['campaigns']) ? (string) $filters['campaigns'] : null,
            true,
        );
        if ($selectedCampaignCodes === []) {
            return $this->unavailableDashboard($crmCampaign, $period, $filters, $scope, 'No permitted VICIdial campaigns matched the selected filter.');
        }
        $dispositionScope = (string) ($filters['disposition_scope'] ?? 'all');
        $campaignFilter = $selectedCampaignCodes === null
            ? ($filters['campaigns'] ?? '---ALL---')
            : implode('|', $selectedCampaignCodes);
        $params = [
            'campaigns' => $campaignFilter,
            'ingroups' => $filters['ingroups'] ?? null,
            'disposition_scope' => $filters['disposition_scope'] ?? 'all',
            'query_date' => $period['start']->format('Y-m-d'),
            'end_date' => $period['end']->format('Y-m-d'),
            'timezone' => $period['timezone'],
            'datetime_start' => $this->vicidialDateTime($period['start']),
            'datetime_end' => $this->vicidialDateTime($period['end'], true),
        ];
        $current = $this->reportingService->historicalSnapshot(
            $user,
            $crmCampaign,
            $params,
            ['connect_timeout' => 3, 'timeout' => 10, 'retry_times' => 1],
        );
        $callStatus = $this->parseCallStatus($current['call_status'] ?? null, $selectedCampaignCodes, $dispositionScope);
        $agents = $this->parseAgentStats($current['agent_stats'] ?? null, $selectedCampaignCodes);
        $dispositions = $this->parseDispositions(
            $current['call_dispo'] ?? null,
            $dispositionScope,
            $selectedCampaignCodes,
        );
        $summary = $this->summary($callStatus, $agents, $dispositions);
        $callStatus['campaigns'] = $this->addCampaignDispositionRates($callStatus['campaigns'], $dispositions);

        $comparisonMode = (string) ($filters['comparison'] ?? 'none');
        $comparison = $this->comparison(
            $user,
            $crmCampaign,
            $params,
            $period,
            $comparisonMode,
            $summary,
            $selectedCampaignCodes,
        );
        $sourceStatus = $this->sourceStatus($current, [
            'call_status' => $callStatus,
            'agent_stats' => $agents,
            'call_dispo' => $dispositions,
        ]);

        return [
            'filters' => [
                'crm_campaign' => $crmCampaign,
                'campaigns' => $params['campaigns'],
                'ingroups' => $params['ingroups'],
                'query_date' => $period['start']->format('Y-m-d'),
                'end_date' => $period['end']->format('Y-m-d'),
                'timezone' => $period['timezone'],
                'disposition_scope' => $filters['disposition_scope'] ?? 'all',
                'comparison' => $comparisonMode,
            ],
            'availability' => $sourceStatus,
            'summary' => $summary,
            'comparison' => $comparison,
            'call_volume' => $callStatus['call_volume'],
            'status_totals' => $callStatus['status_totals'] ?? [],
            'status_state' => $callStatus['status_state'] ?? ($callStatus['state'] ?? 'unavailable'),
            'campaign_state' => $callStatus['state'] ?? 'unavailable',
            'campaigns' => $callStatus['campaigns'],
            'dispositions' => $dispositions['pareto'],
            'disposition_rows' => $dispositions['rows'],
            'disposition_summary' => [
                'total_calls' => $dispositions['total_calls'],
                'contacted_calls' => $dispositions['group_totals']['contacted'] ?? null,
                'state' => $dispositions['state'],
            ],
            'funnel' => $this->funnel($summary['total_calls'], $callStatus['answered_calls'], $dispositions['code_totals'], $dispositions['state']),
            'agents' => $agents['rows'],
            'agent_summary' => $agents['summary'],
            'time_distribution' => $agents['time_distribution'],
            'campaign_scope' => $scope->toArray(true),
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $period
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function unavailableDashboard(
        string $crmCampaign,
        array $period,
        array $filters,
        VicidialCampaignScope $scope,
        ?string $message = null,
    ): array {
        $message ??= $scope->server === null
            ? "No VICIdial server is configured for campaign '{$crmCampaign}'."
            : "No enabled VICIdial campaigns are mapped to CRM campaign '{$crmCampaign}'.";
        $emptySummary = [
            'total_calls' => null,
            'answered_calls' => null,
            'answer_rate' => null,
            'contact_rate' => null,
            'average_talk_time_seconds' => null,
            'agents_with_activity' => null,
            'calls_per_agent' => null,
        ];

        return [
            'filters' => [
                'crm_campaign' => $crmCampaign,
                'campaigns' => $filters['campaigns'] ?? '---ALL---',
                'ingroups' => $filters['ingroups'] ?? null,
                'query_date' => $period['start']->format('Y-m-d'),
                'end_date' => $period['end']->format('Y-m-d'),
                'timezone' => $period['timezone'],
                'disposition_scope' => $filters['disposition_scope'] ?? 'all',
                'comparison' => $filters['comparison'] ?? 'none',
            ],
            'availability' => [
                'status' => 'unavailable',
                'available_sections' => 0,
                'failed_sections' => 0,
                'message' => $message,
                'sources' => [],
            ],
            'summary' => $emptySummary,
            'comparison' => ['enabled' => false, 'mode' => 'none', 'period' => null, 'metrics' => []],
            'call_volume' => ['labels' => [], 'values' => [], 'grouping' => 'hourly', 'state' => 'unavailable'],
            'status_totals' => [],
            'status_state' => 'unavailable',
            'campaign_state' => 'unavailable',
            'campaigns' => [],
            'dispositions' => ['labels' => [], 'values' => [], 'percentages' => [], 'state' => 'unavailable'],
            'disposition_rows' => [],
            'disposition_summary' => ['total_calls' => null, 'contacted_calls' => null, 'state' => 'unavailable'],
            'funnel' => [],
            'agents' => [],
            'agent_summary' => [
                'agents_with_activity' => null,
                'total_calls' => null,
                'average_talk_time_seconds' => null,
                'total_talk_time_seconds' => null,
                'total_pause_time_seconds' => null,
            ],
            'time_distribution' => [
                'talk_seconds' => null,
                'pause_seconds' => null,
                'ready_seconds' => null,
                'other_seconds' => null,
                'states' => [
                    'talk_seconds' => 'unavailable',
                    'pause_seconds' => 'unavailable',
                    'ready_seconds' => 'unavailable',
                    'other_seconds' => 'unavailable',
                ],
            ],
            'campaign_scope' => $scope->toArray(true),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{start: Carbon, end: Carbon, timezone: string}
     */
    protected function period(array $filters): array
    {
        $timezone = $this->reportTimezone($filters['timezone'] ?? null);
        $start = Carbon::parse((string) ($filters['query_date'] ?? now($timezone)->format('Y-m-d')), $timezone)->startOfDay();
        $end = Carbon::parse((string) ($filters['end_date'] ?? $start->format('Y-m-d')), $timezone)->startOfDay();
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end, 'timezone' => $timezone];
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
        ?array $allowedCampaignCodes = null,
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
            'timezone' => $period['timezone'],
            'datetime_start' => $this->vicidialDateTime($previousStart),
            'datetime_end' => $this->vicidialDateTime($previousEnd, true),
        ];
        $previous = $this->reportingService->historicalSnapshot(
            $user,
            $crmCampaign,
            $previousParams,
            ['connect_timeout' => 3, 'timeout' => 10, 'retry_times' => 1],
        );
        $previousStatus = $this->parseCallStatus(
            $previous['call_status'] ?? null,
            $allowedCampaignCodes,
            (string) ($params['disposition_scope'] ?? 'all'),
        );
        $previousAgents = $this->parseAgentStats($previous['agent_stats'] ?? null, $allowedCampaignCodes);
        $previousDispositions = $this->parseDispositions(
            $previous['call_dispo'] ?? null,
            (string) ($params['disposition_scope'] ?? 'all'),
            $allowedCampaignCodes,
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
            'availability' => $this->sourceStatus($previous, [
                'call_status' => $previousStatus,
                'agent_stats' => $previousAgents,
                'call_dispo' => $previousDispositions,
            ]),
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
    protected function sourceStatus(array $results, array $parserResults = []): array
    {
        $available = 0;
        $failed = 0;
        $incomplete = 0;
        foreach ($results as $name => $result) {
            if (! $result instanceof OperationResult) {
                continue;
            }
            $parser = $parserResults[$name] ?? [];
            $state = is_array($parser) ? (string) ($parser['state'] ?? '') : '';
            if ($result->success && in_array($state, ['data', 'confirmed_zero', 'degraded'], true)) {
                $available++;
            }
            if (! $result->success || in_array($state, ['unsupported', 'parse_failure', 'transport_failure', 'permission_failure', 'degraded'], true)) {
                $failed++;
            }
            if ($state === 'empty') {
                $incomplete++;
            }
        }
        $status = $available === 0 ? 'unavailable' : ($failed > 0 || $incomplete > 0 ? 'degraded' : 'live');

        $sources = [];
        foreach ($results as $name => $result) {
            if (! $result instanceof OperationResult) {
                continue;
            }
            $meta = $result->meta;
            $parser = $parserResults[$name] ?? [];
            $parserState = (string) ($parser['state'] ?? '');
            $classification = (string) ($meta['classification'] ?? ($result->success ? 'OK' : 'UNKNOWN'));
            $sources[$name] = [
                'status' => $this->sourceDisplayStatus($result, $parserState),
                'state' => $parserState !== '' ? $parserState : $this->classificationState($classification),
                'classification' => $classification,
                'http_status' => $meta['http_status'] ?? null,
                'content_type' => $meta['content_type'] ?? null,
                'response_bytes' => $meta['response_bytes'] ?? null,
                'duration_ms' => $meta['duration_ms'] ?? null,
                'parsed_rows' => $meta['parsed_rows'] ?? 0,
                'parsed_metrics' => $parser['parsed_metrics'] ?? null,
                'message' => $result->success ? null : $result->message,
            ];
        }

        return [
            'status' => $status,
            'available_sections' => $available,
            'failed_sections' => $failed + $incomplete,
            'message' => $status === 'live' ? null : 'Some historical VICIdial report data is unavailable. Retry the report or verify the campaign server permissions.',
            'sources' => $sources,
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
        $callDataAvailable = (bool) ($callStatus['available'] ?? false);
        $agentDataAvailable = (bool) ($agents['available'] ?? false);
        $totalCalls = $callDataAvailable ? ($callStatus['total_calls'] ?? null) : null;
        $answeredCalls = $callDataAvailable ? ($callStatus['answered_calls'] ?? null) : null;
        $contacted = $dispositions['group_totals']['contacted'] ?? null;
        $averageTalk = $agentDataAvailable ? $agents['summary']['average_talk_time_seconds'] : null;
        $agentCount = $agentDataAvailable ? ($agents['summary']['agents_with_activity'] ?? null) : null;

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'answer_rate' => $totalCalls !== null && $answeredCalls !== null && $totalCalls > 0
                ? round(($answeredCalls / $totalCalls) * 100, 2)
                : ($totalCalls === 0 && $answeredCalls !== null ? 0 : null),
            'contact_rate' => is_numeric($contacted) && $totalCalls !== null && $totalCalls > 0
                ? round(((float) $contacted / $totalCalls) * 100, 2)
                : (is_numeric($contacted) && $totalCalls === 0 ? 0 : null),
            'average_talk_time_seconds' => $averageTalk,
            'agents_with_activity' => $agentCount,
            'calls_per_agent' => $agentCount !== null && $agentCount > 0 && $totalCalls !== null
                ? round($totalCalls / $agentCount, 2)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseCallStatus(
        ?OperationResult $result,
        ?array $allowedCampaignCodes = null,
        string $dispositionScope = 'all',
    ): array {
        $empty = [
            'available' => false,
            'state' => $this->resultState($result),
            'total_calls' => null,
            'answered_calls' => null,
            'campaigns' => [],
            'status_totals' => [],
            'status_state' => $this->resultState($result),
            'call_volume' => ['labels' => [], 'values' => [], 'grouping' => 'hourly', 'state' => $this->resultState($result)],
        ];
        if ($result === null || ! $result->success) {
            return $empty;
        }
        $rows = $this->rows($result);
        if ($rows === []) {
            return $empty;
        }

        [$header, $dataRows] = $this->callStatusRows($rows);
        if ($dataRows === []) {
            $empty['state'] = $header === null ? 'empty' : 'empty';

            return $empty;
        }
        if ($header === null) {
            $indexes = [
                'campaign' => 0,
                'total' => 1,
                'answered' => 2,
                'hourly' => 3,
                'status' => 4,
            ];
        } else {
            $indexes = [
                'campaign' => $this->findHeaderIndex($header, ['campaign_id_ingroup', 'campaign_ingroup', 'campaign_id', 'campaign', 'campaign_code']),
                'total' => $this->findHeaderIndex($header, ['total_calls', 'total_call', 'total', 'calls']),
                'answered' => $this->findHeaderIndex($header, ['human_answered_calls', 'answered_calls', 'answered', 'human_answered']),
                'hourly' => $this->findHeaderIndex($header, ['hourly_breakdown', 'hourly', 'call_volume', 'hour_breakdown']),
                'status' => $this->findHeaderIndex($header, ['status_breakdown', 'status', 'statuses']),
            ];
            if ($indexes['campaign'] === null || $indexes['total'] === null || $indexes['answered'] === null) {
                return [...$empty, 'state' => 'unsupported'];
            }
        }

        $campaignMap = [];
        $hourMap = [];
        $statusMap = [];
        $allowed = $this->campaignDisplayMap($allowedCampaignCodes);
        $systemCodes = $this->systemDispositionCodes();
        $parseFailures = 0;
        $hasHourly = false;
        $hasStatusData = false;
        $hasStatuses = false;
        foreach ($dataRows as $row) {
            $rawLabel = trim((string) ($row[$indexes['campaign']] ?? ''));
            $label = trim(explode('/', $rawLabel, 2)[0]);
            if ($label === '') {
                $parseFailures++;

                continue;
            }
            if (strtoupper($label) === 'TOTAL') {
                continue;
            }
            $campaignCode = $this->canonicalCampaignCode($label, $allowed);
            if ($allowedCampaignCodes !== null && $campaignCode === null) {
                continue;
            }
            $campaignCode ??= strtoupper($label);
            $total = $this->integer($row[$indexes['total']] ?? null);
            $answered = $this->integer($row[$indexes['answered']] ?? null);
            if ($total === null || $answered === null) {
                $parseFailures++;

                continue;
            }
            if (! isset($campaignMap[$campaignCode])) {
                $campaignMap[$campaignCode] = [
                    'campaign' => $campaignCode,
                    'total_calls' => 0,
                    'answered_calls' => 0,
                    'answer_rate' => null,
                    'contact_rate' => null,
                    'top_status' => null,
                    'peak_hour' => null,
                ];
            }
            $campaignMap[$campaignCode]['total_calls'] += $total;
            $campaignMap[$campaignCode]['answered_calls'] += $answered;
            $hourlyPairs = $indexes['hourly'] === null ? [] : $this->pairs($row[$indexes['hourly']] ?? '', ',', '-');
            foreach ($hourlyPairs as $pair) {
                $hour = str_pad((string) ((int) $pair['label']), 2, '0', STR_PAD_LEFT);
                $hourMap[$hour] = ($hourMap[$hour] ?? 0) + $pair['value'];
                $hasHourly = true;
            }
            $statusPairs = $indexes['status'] === null ? [] : $this->pairs($row[$indexes['status']] ?? '', ',', '-');
            foreach ($statusPairs as $pair) {
                $status = $this->normalizeCode($pair['label']);
                $hasStatusData = true;
                if (! $this->dispositionMatchesScope($status, $systemCodes, $dispositionScope)) {
                    continue;
                }
                $statusMap[$status] = ($statusMap[$status] ?? 0) + $pair['value'];
                $hasStatuses = true;
            }
        }
        if ($campaignMap === []) {
            return [...$empty, 'state' => $parseFailures > 0 ? 'parse_failure' : 'empty'];
        }
        foreach ($campaignMap as &$campaign) {
            $campaign['answer_rate'] = $campaign['total_calls'] > 0
                ? round(($campaign['answered_calls'] / $campaign['total_calls']) * 100, 2)
                : ($campaign['total_calls'] === 0 ? 0 : null);
            $campaign['peak_hour'] = $this->peakKey($this->campaignBreakdown($campaign, $dataRows, $indexes['campaign'], $indexes['hourly']));
        }
        unset($campaign);
        ksort($hourMap);
        arsort($statusMap);

        $totalCalls = array_sum(array_column($campaignMap, 'total_calls'));
        $answeredCalls = array_sum(array_column($campaignMap, 'answered_calls'));
        $state = $totalCalls === 0 ? 'confirmed_zero' : 'data';
        $hourlyValues = $hasHourly ? array_map(static fn (string $hour): int => $hourMap[$hour] ?? 0, $this->hourLabels()) : [];
        foreach ($campaignMap as &$campaign) {
            $campaign['top_status'] = $this->topCampaignStatus(
                $campaign['campaign'],
                $dataRows,
                $indexes['campaign'],
                $indexes['status'],
                $systemCodes,
                $dispositionScope,
            );
        }
        unset($campaign);

        return [
            'available' => true,
            'state' => $parseFailures > 0 ? 'degraded' : $state,
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'campaigns' => array_values($campaignMap),
            'status_totals' => $statusMap,
            'status_state' => $hasStatuses
                ? ($totalCalls === 0 ? 'confirmed_zero' : 'data')
                : ($hasStatusData ? 'confirmed_zero' : 'unsupported'),
            'call_volume' => [
                'labels' => $hasHourly ? $this->hourLabels() : [],
                'values' => $hourlyValues,
                'grouping' => 'hourly',
                'state' => $hasHourly ? ($totalCalls === 0 ? 'confirmed_zero' : 'data') : 'unsupported',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseAgentStats(?OperationResult $result, ?array $allowedCampaignCodes = null): array
    {
        $empty = [
            'available' => false,
            'state' => $this->resultState($result),
            'rows' => [],
            'summary' => [
                'agents_with_activity' => null,
                'total_calls' => null,
                'average_talk_time_seconds' => null,
                'total_talk_time_seconds' => null,
                'total_pause_time_seconds' => null,
            ],
            'time_distribution' => [
                'talk_seconds' => null,
                'pause_seconds' => null,
                'ready_seconds' => null,
                'other_seconds' => null,
                'states' => [
                    'talk_seconds' => 'unsupported',
                    'pause_seconds' => 'unsupported',
                    'ready_seconds' => 'unsupported',
                    'other_seconds' => 'unsupported',
                ],
            ],
        ];
        if ($result === null || ! $result->success) {
            return $empty;
        }
        $rows = $this->rows($result);
        if ($rows === []) {
            return $empty;
        }
        $headers = array_map(fn (mixed $header): string => $this->key($header), $rows[0]);
        if ($this->findHeaderIndex($headers, ['user', 'agent_user', 'username']) === null
            || $this->findHeaderIndex($headers, ['calls', 'calls_today', 'total_calls']) === null) {
            return [...$empty, 'state' => 'unsupported'];
        }
        $agents = [];
        $totalTalk = 0;
        $totalPause = 0;
        $totalReady = 0;
        $totalOther = 0;
        $hasTalk = false;
        $hasPause = false;
        $hasReady = false;
        $hasOther = false;
        $parseFailures = 0;
        $allowed = $this->campaignDisplayMap($allowedCampaignCodes);
        foreach (array_slice($rows, 1) as $row) {
            $data = [];
            foreach ($headers as $index => $header) {
                $data[$header] = $row[$index] ?? '';
            }
            $user = trim((string) $this->firstValue($data, ['user', 'agent_user', 'username']));
            if ($user === '') {
                $parseFailures++;

                continue;
            }
            $agentKey = strtolower($user);
            $rawCampaignCode = trim((string) $this->firstValue($data, ['campaign', 'campaign_id', 'campaign_id_ingroup', 'campaign_code']));
            $campaignCode = $this->canonicalCampaignCode($rawCampaignCode, $allowed);
            if ($allowedCampaignCodes !== null && $campaignCode === null) {
                continue;
            }
            $campaignCode ??= strtoupper($rawCampaignCode);
            $calls = $this->integer($this->firstValue($data, ['calls', 'calls_today', 'total_calls']));
            if ($calls === null) {
                $parseFailures++;

                continue;
            }
            $talkRaw = $this->firstValue($data, ['total_talk_time', 'talk_time']);
            $talk = $this->seconds($talkRaw);
            $avgTalk = $this->seconds($this->firstValue($data, ['avg_talk_time', 'average_talk_time']));
            $talk = $talk ?? ($avgTalk !== null ? $avgTalk * $calls : null);
            $pause = $this->seconds($this->firstValue($data, ['pause_time', 'total_pause_time']));
            $ready = $this->seconds($this->firstValue($data, ['ready_time', 'total_ready_time', 'available_time', 'ready_seconds']));
            $other = $this->seconds($this->firstValue($data, ['other_time', 'total_other_time', 'other_seconds']));
            $hasReadyField = $this->hasAnyKey($data, ['ready_time', 'total_ready_time', 'available_time', 'ready_seconds']);
            $hasOtherField = $this->hasAnyKey($data, ['other_time', 'total_other_time', 'other_seconds']);
            if ($hasReadyField) {
                $hasReady = true;
                if ($ready === null) {
                    $parseFailures++;
                }
            }
            if ($hasOtherField) {
                $hasOther = true;
                if ($other === null) {
                    $parseFailures++;
                }
            }
            if (! isset($agents[$agentKey])) {
                $agents[$agentKey] = [
                    'user' => $user,
                    'full_name' => $this->firstValue($data, ['full_name', 'name']) ?: $user,
                    'user_group' => $this->firstValue($data, ['user_group', 'group']) ?: '',
                    'calls' => 0,
                    'answered' => null,
                    'contacted' => null,
                    'total_talk_time_seconds' => 0,
                    'total_wait_time_seconds' => 0,
                    'pause_time_seconds' => 0,
                    'pause_pct' => null,
                    'campaigns' => [],
                ];
            }
            $agents[$agentKey]['calls'] += $calls;
            $answered = $this->integer($this->firstValue($data, ['answered', 'answered_calls']));
            if ($answered !== null) {
                $agents[$agentKey]['answered'] = ($agents[$agentKey]['answered'] ?? 0) + $answered;
            }
            $agents[$agentKey]['total_talk_time_seconds'] += $talk ?? 0;
            $agents[$agentKey]['total_wait_time_seconds'] += $this->seconds($this->firstValue($data, ['total_wait_time', 'wait_time'])) ?? 0;
            $agents[$agentKey]['pause_time_seconds'] += $pause ?? 0;
            if ($talk !== null) {
                $hasTalk = true;
                $totalTalk += $talk;
            }
            if ($pause !== null) {
                $hasPause = true;
                $totalPause += $pause;
            }
            $agents[$agentKey]['pause_pct'] = $this->percent($this->firstValue($data, ['pause_pct', 'pause_percent']));
            if ($campaignCode !== '') {
                $agents[$agentKey]['campaigns'][$campaignCode] = ($agents[$agentKey]['campaigns'][$campaignCode] ?? 0) + $calls;
            }
            if ($ready !== null) {
                $totalReady += $ready;
            }
            if ($other !== null) {
                $totalOther += $other;
            }
        }
        if ($agents === []) {
            return [...$empty, 'state' => $parseFailures > 0 ? 'parse_failure' : 'empty'];
        }
        foreach ($agents as &$agent) {
            $agent['answer_rate'] = is_numeric($agent['answered']) && $agent['calls'] > 0
                ? round(($agent['answered'] / $agent['calls']) * 100, 2)
                : ($agent['calls'] === 0 && $agent['answered'] !== null ? 0 : null);
            $agent['avg_talk_time_seconds'] = $agent['calls'] > 0 && $agent['total_talk_time_seconds'] > 0
                ? round($agent['total_talk_time_seconds'] / $agent['calls'], 1)
                : null;
        }
        unset($agent);
        usort($agents, static fn (array $left, array $right): int => $right['calls'] <=> $left['calls']);
        $totalCalls = array_sum(array_column($agents, 'calls'));
        $state = $totalCalls === 0 ? 'confirmed_zero' : 'data';

        return [
            'available' => true,
            'state' => $parseFailures > 0 ? 'degraded' : $state,
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
                'ready_seconds' => $hasReady && $parseFailures === 0 ? $totalReady : null,
                'other_seconds' => $hasOther && $parseFailures === 0 ? $totalOther : null,
                'states' => [
                    'talk_seconds' => $hasTalk ? 'data' : 'unsupported',
                    'pause_seconds' => $hasPause ? 'data' : 'unsupported',
                    'ready_seconds' => $hasReady && $parseFailures === 0 ? 'data' : ($hasReady ? 'parse_failure' : 'unsupported'),
                    'other_seconds' => $hasOther && $parseFailures === 0 ? 'data' : ($hasOther ? 'parse_failure' : 'unsupported'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseDispositions(?OperationResult $result, string $scope, ?array $allowedCampaignCodes = null): array
    {
        $empty = [
            'state' => $this->resultState($result),
            'rows' => [],
            'pareto' => ['labels' => [], 'values' => [], 'percentages' => [], 'state' => 'empty'],
            'code_totals' => [],
            'group_totals' => [],
            'group_campaign_totals' => [],
            'total_calls' => null,
        ];
        if ($result === null || ! $result->success) {
            return $empty;
        }
        $rows = $this->rows($result);
        if ($rows === []) {
            return $empty;
        }
        $headers = array_map(fn (mixed $header): string => $this->key($header), $rows[0]);
        $campaignIndex = $this->findHeaderIndex($headers, ['campaign_id', 'campaign', 'campaign_code', 'campaign_ingroup', 'campaign_id_ingroup']);
        $ingroupIndex = $this->findHeaderIndex($headers, ['ingroup', 'in_group', 'group']);
        if ($campaignIndex === null) {
            return [...$empty, 'state' => 'unsupported'];
        }
        $metricColumns = [];
        foreach ($headers as $index => $header) {
            if ($index === $campaignIndex || $index === $ingroupIndex || $header === '' || $this->isDispositionTotalColumn($header)) {
                continue;
            }
            $metricColumns[] = ['index' => $index, 'label' => trim((string) ($rows[0][$index] ?? $header))];
        }
        if ($metricColumns === []) {
            return [...$empty, 'state' => 'unsupported'];
        }
        $systemCodes = $this->systemDispositionCodes();
        $scopedMetricColumns = array_values(array_filter(
            $metricColumns,
            function (array $metricColumn) use ($systemCodes, $scope): bool {
                return $this->dispositionMatchesScope($metricColumn['label'], $systemCodes, $scope);
            },
        ));
        if ($scopedMetricColumns === []) {
            return [
                ...$empty,
                'state' => 'confirmed_zero',
                'pareto' => ['labels' => [], 'values' => [], 'percentages' => [], 'state' => 'confirmed_zero'],
                'total_calls' => 0,
            ];
        }
        $codeTotals = [];
        $codeLabels = [];
        $reportRows = [];
        $campaignCodeTotals = [];
        $parseFailures = 0;
        $allowed = $this->campaignDisplayMap($allowedCampaignCodes);
        foreach (array_slice($rows, 1) as $index => $row) {
            $rawCampaign = trim((string) ($row[$campaignIndex] ?? ''));
            $campaign = trim(explode('/', $rawCampaign, 2)[0]);
            if ($campaign === '') {
                $parseFailures++;

                continue;
            }
            if ($this->isDispositionTotalRow($campaign)) {
                continue;
            }
            $campaignCode = $this->canonicalCampaignCode($campaign, $allowed);
            if ($allowedCampaignCodes !== null && $campaignCode === null) {
                continue;
            }
            $campaignCode ??= $this->normalizeCode($campaign);
            $metrics = [];
            foreach ($scopedMetricColumns as $metricColumn) {
                $metricIndex = $metricColumn['index'];
                $label = $metricColumn['label'];
                $code = $this->normalizeCode($label);
                $isSystem = isset($systemCodes[$code]);
                if (! $this->dispositionMatchesScope($code, $systemCodes, $scope)) {
                    continue;
                }
                $value = $this->displayNumber($row[$metricIndex] ?? null);
                $metrics[] = ['label' => $code, 'value' => $value, 'percentage' => null, 'system' => $isSystem];
                if ($value === null) {
                    $parseFailures++;

                    continue;
                }
                $codeLabels[$code] ??= $code;
                $codeTotals[$code] = ($codeTotals[$code] ?? 0) + $value;
                $campaignCodeTotals[$campaignCode][$code] = ($campaignCodeTotals[$campaignCode][$code] ?? 0) + $value;
            }
            if ($metrics === []) {
                continue;
            }
            usort($metrics, static fn (array $left, array $right): int => ($right['value'] ?? -1) <=> ($left['value'] ?? -1));
            $numericMetrics = array_filter($metrics, static fn (array $metric): bool => is_numeric($metric['value']));
            $topMetric = array_values($numericMetrics)[0] ?? null;
            $reportRows[] = [
                'campaign' => $campaignCode,
                'total_calls' => count($numericMetrics) === count($metrics) ? array_sum(array_column($metrics, 'value')) : null,
                'top_disposition' => $topMetric['label'] ?? null,
                'metrics' => $metrics,
            ];
        }
        if ($reportRows === []) {
            return [...$empty, 'state' => $parseFailures > 0 ? 'parse_failure' : 'empty'];
        }
        arsort($codeTotals);
        $topCodes = array_slice($codeTotals, 0, 10, true);
        $other = array_sum(array_slice($codeTotals, 10, null, true));
        if ($other > 0) {
            $topCodes['OTHER'] = $other;
            $codeLabels['OTHER'] = 'Other';
        }
        $total = $codeTotals === [] && $parseFailures > 0 ? null : array_sum($codeTotals);
        $pareto = ['labels' => array_map(fn (string $code): string => $codeLabels[$code] ?? $code, array_keys($topCodes)), 'values' => array_values($topCodes), 'percentages' => array_map(
            fn (int|float $value): float => is_numeric($total) && $total > 0 ? round(($value / $total) * 100, 2) : 0,
            array_values($topCodes),
        ), 'state' => $total === null ? 'parse_failure' : ($total === 0 ? 'confirmed_zero' : 'data')];
        $groups = config('vicidial.report_disposition_groups', []);
        $groupCampaignTotals = [];
        $groupTotals = [];
        foreach (['contacted', 'qualified', 'successful'] as $group) {
            $codes = array_map([$this, 'normalizeCode'], $groups[$group] ?? []);
            if ($codes === []) {
                continue;
            }
            $groupTotals[$group] = null;
            if (count(array_intersect($codes, array_keys($codeTotals))) === count($codes)) {
                $groupTotals[$group] = array_sum(array_intersect_key(
                    $codeTotals,
                    array_flip($codes),
                ));
                $groupCampaignTotals[$group] = [];
                foreach ($campaignCodeTotals as $campaignCode => $campaignMetrics) {
                    $groupCampaignTotals[$group][$campaignCode] = array_sum(array_intersect_key(
                        $campaignMetrics,
                        array_flip($codes),
                    ));
                }
            }
        }

        return [
            'state' => $parseFailures > 0 ? 'degraded' : ($total === 0 ? 'confirmed_zero' : 'data'),
            'rows' => $reportRows,
            'pareto' => $pareto,
            'code_totals' => array_combine(
                array_map(fn (string $code): string => $codeLabels[$code] ?? $code, array_keys($codeTotals)),
                array_values($codeTotals),
            ) ?: [],
            'group_totals' => $groupTotals,
            'group_campaign_totals' => $groupCampaignTotals,
            'total_calls' => $total,
        ];
    }

    /**
     * @param  array<string, int|float>  $codeTotals
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function funnel(?int $totalCalls, ?int $answeredCalls, array $codeTotals, ?string $dispositionState = null): array
    {
        if ($totalCalls === null || $answeredCalls === null || ! in_array($dispositionState, ['data', 'confirmed_zero'], true)) {
            return [];
        }
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
            $values[$group] = null;
            if (count(array_intersect($codes, array_keys($codeTotals))) !== count($codes)) {
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
     * @return array{0: array<int, string>|null, 1: array<int, array<int, string>>}
     */
    protected function callStatusRows(array $rows): array
    {
        $headers = array_map(fn (mixed $header): string => $this->key($header), $rows[0] ?? []);
        $hasCampaign = $this->findHeaderIndex($headers, ['campaign_id_ingroup', 'campaign_ingroup', 'campaign_id', 'campaign', 'campaign_code']) !== null;
        $hasTotal = $this->findHeaderIndex($headers, ['total_calls', 'total_call', 'total', 'calls']) !== null;
        $hasAnswered = $this->findHeaderIndex($headers, ['human_answered_calls', 'answered_calls', 'answered', 'human_answered']) !== null;

        return $hasCampaign && $hasTotal && $hasAnswered
            ? [$headers, array_slice($rows, 1)]
            : [null, $rows];
    }

    protected function findHeaderIndex(array $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $alias = $this->key($alias);
            foreach ($headers as $index => $header) {
                if ($header === $alias) {
                    return $index;
                }
            }
        }
        foreach ($aliases as $alias) {
            $alias = $this->key($alias);
            foreach ($headers as $index => $header) {
                if (str_contains($header, $alias)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>|null  $codes
     * @return array<string, string>
     */
    protected function campaignDisplayMap(?array $codes): array
    {
        $map = [];
        foreach ($codes ?? [] as $code) {
            $code = trim((string) $code);
            if ($code !== '') {
                $map[strtolower($code)] = $code;
            }
        }

        return $map;
    }

    protected function canonicalCampaignCode(string $code, array $allowed): ?string
    {
        $normalized = strtolower(trim($code));

        return $normalized === '' ? null : ($allowed[$normalized] ?? null);
    }

    protected function firstValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    protected function hasAnyKey(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function hourLabels(): array
    {
        return array_map(static fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT), range(0, 23));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array{campaign: int, hourly: int|null}  $indexes
     * @return array<string, int>
     */
    protected function campaignBreakdown(array $campaign, array $rows, int $campaignIndex, ?int $hourlyIndex): array
    {
        if ($hourlyIndex === null) {
            return [];
        }
        $breakdown = [];
        foreach ($rows as $row) {
            $label = trim(explode('/', (string) ($row[$campaignIndex] ?? ''), 2)[0]);
            if (strtolower($label) !== strtolower((string) $campaign['campaign'])) {
                continue;
            }
            foreach ($this->pairs($row[$hourlyIndex] ?? '', ',', '-') as $pair) {
                $hour = str_pad((string) ((int) $pair['label']), 2, '0', STR_PAD_LEFT);
                $breakdown[$hour] = ($breakdown[$hour] ?? 0) + $pair['value'];
            }
        }

        return $breakdown;
    }

    protected function peakKey(array $values): ?string
    {
        if ($values === []) {
            return null;
        }
        arsort($values);

        return (string) array_key_first($values);
    }

    protected function topCampaignStatus(
        string $campaign,
        array $rows,
        int $campaignIndex,
        ?int $statusIndex,
        array $systemCodes = [],
        string $dispositionScope = 'all',
    ): ?string {
        if ($statusIndex === null) {
            return null;
        }
        $totals = [];
        foreach ($rows as $row) {
            $label = trim(explode('/', (string) ($row[$campaignIndex] ?? ''), 2)[0]);
            if (strtolower($label) !== strtolower($campaign)) {
                continue;
            }
            foreach ($this->pairs($row[$statusIndex] ?? '', ',', '-') as $pair) {
                $status = $this->normalizeCode($pair['label']);
                if (! $this->dispositionMatchesScope($status, $systemCodes, $dispositionScope)) {
                    continue;
                }
                $totals[$status] = ($totals[$status] ?? 0) + $pair['value'];
            }
        }
        if ($totals === []) {
            return null;
        }
        arsort($totals);

        return (string) array_key_first($totals);
    }

    /**
     * @return array<string, string>
     */
    protected function systemDispositionCodes(): array
    {
        $codes = array_map(
            fn (mixed $code): string => $this->normalizeCode($code),
            (array) config('vicidial.report_system_disposition_codes', []),
        );

        return array_fill_keys(array_values(array_filter($codes)), 'system');
    }

    /**
     * @param  array<string, string>  $systemCodes
     */
    protected function dispositionMatchesScope(string $code, array $systemCodes, string $scope): bool
    {
        $isSystem = isset($systemCodes[$this->normalizeCode($code)]);

        return match ($scope) {
            'exclude_system' => ! $isSystem,
            'system_only' => $isSystem,
            default => true,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $campaigns
     * @param  array<string, mixed>  $dispositions
     * @return array<int, array<string, mixed>>
     */
    protected function addCampaignDispositionRates(array $campaigns, array $dispositions): array
    {
        $contactedByCampaign = $dispositions['group_campaign_totals']['contacted'] ?? null;
        foreach ($campaigns as &$campaign) {
            $code = (string) ($campaign['campaign'] ?? '');
            $contacted = is_array($contactedByCampaign) && array_key_exists($code, $contactedByCampaign)
                ? $contactedByCampaign[$code]
                : null;
            $campaign['contact_rate'] = is_numeric($contacted) && is_numeric($campaign['total_calls']) && $campaign['total_calls'] > 0
                ? round(((float) $contacted / (float) $campaign['total_calls']) * 100, 2)
                : (is_numeric($contacted) && $campaign['total_calls'] === 0 ? 0 : null);
        }
        unset($campaign);

        return $campaigns;
    }

    protected function reportTimezone(?string $requested): string
    {
        $timezone = trim((string) ($requested ?: config('vicidial.report_timezone', config('app.timezone', 'UTC'))));

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            return (string) config('app.timezone', 'UTC');
        }

        return $timezone;
    }

    protected function vicidialDateTime(Carbon $date, bool $endOfDay = false): string
    {
        return $date->format('Y-m-d').($endOfDay ? '+23:59:59' : '+00:00:00');
    }

    protected function resultState(?OperationResult $result): string
    {
        if ($result === null) {
            return 'unavailable';
        }
        if (! $result->success) {
            return $this->classificationState((string) ($result->meta['classification'] ?? 'UNKNOWN'));
        }
        if (($result->meta['classification'] ?? null) === 'REPORT_EMPTY' || $this->rows($result) === []) {
            return 'empty';
        }

        return 'data';
    }

    protected function classificationState(string $classification): string
    {
        return match ($classification) {
            'REPORT_EMPTY' => 'empty',
            'PARSE_ERROR', 'REPORT_HTML_CHANGED' => 'parse_failure',
            'PERMISSION_DENIED' => 'permission_failure',
            'AUTHENTICATION_FAILED', 'NETWORK_TIMEOUT', 'CONNECTION_REFUSED', 'NETWORK_ERROR', 'SERVER_ERROR', 'NOT_CONFIGURED' => 'transport_failure',
            default => 'unavailable',
        };
    }

    protected function sourceDisplayStatus(OperationResult $result, string $parserState): string
    {
        if (! $result->success) {
            return 'unavailable';
        }
        if ($parserState === 'degraded') {
            return 'degraded';
        }
        if ($parserState === 'empty') {
            return 'empty';
        }
        if (in_array($parserState, ['unsupported', 'parse_failure'], true)) {
            return 'unavailable';
        }

        return 'healthy';
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
        $value = preg_replace('/[^0-9.-].*$/', '', trim((string) $value));

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    protected function integer(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        preg_match('/-?[0-9]+(?:\.[0-9]+)?/', $value, $matches);

        return isset($matches[0]) ? max(0, (int) round((float) $matches[0])) : null;
    }

    protected function displayNumber(mixed $value): ?int
    {
        return $this->integer($value);
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
            $value = $this->integer($parts[1]);
            if ($value === null) {
                continue;
            }
            $pairs[] = ['label' => trim($parts[0]), 'value' => $value];
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

    protected function isDispositionTotalColumn(mixed $value): bool
    {
        return in_array($this->key($value), [
            'total',
            'total_call',
            'total_calls',
            'grand_total',
            'grand_total_call',
            'grand_total_calls',
        ], true);
    }

    protected function isDispositionTotalRow(mixed $value): bool
    {
        return $this->isDispositionTotalColumn($value) || in_array($this->key($value), [
            'all',
            'all_campaigns',
        ], true);
    }

    /**
     * @param  array<int, string>  $allowedCampaignCodes
     */
    protected function campaignCodeIsAllowed(string $campaignCode, array $allowedCampaignCodes): bool
    {
        $campaignCode = strtolower(trim($campaignCode));

        return in_array($campaignCode, array_map('strtolower', $allowedCampaignCodes), true);
    }
}
