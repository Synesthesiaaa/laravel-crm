<?php

namespace App\Services;

use App\Models\FormField;
use App\Repositories\CampaignRepository;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardStatsService
{
    public function __construct(
        protected CampaignRepository $campaignRepository,
        protected DashboardLayoutService $dashboardLayoutService,
        protected DashboardSalesRuleService $dashboardSalesRuleService,
    ) {}

    /**
     * Rolling last 24 hours of form submissions, one bucket per clock hour (uses created_at).
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getLast24HourActivityTrend(string $campaignCode): array
    {
        $ttl = (int) config('dashboard.last_24h_activity_cache_seconds', 120);

        return Cache::remember("activity_trend_24h_{$campaignCode}", max(30, $ttl), function () use ($campaignCode) {
            $tables = $this->resolveAllowedTablesWithCreatedAt($campaignCode);
            if (empty($tables)) {
                return ['labels' => [], 'values' => []];
            }

            $since = now()->copy()->subHours(24);

            /** @var array<string, int> $bucketCounts keys Y-m-d H */
            $bucketCounts = [];
            $labels = [];
            for ($i = 23; $i >= 0; $i--) {
                $h = now()->copy()->subHours($i)->startOfHour();
                $key = $h->format('Y-m-d H');
                $bucketCounts[$key] = 0;
                $labels[] = $h->format('M j H:00');
            }

            foreach ($tables as $t) {
                DB::table($t)
                    ->where('created_at', '>=', $since)
                    ->select('created_at')
                    ->orderBy('id')
                    ->chunk(1000, function ($rows) use (&$bucketCounts) {
                        foreach ($rows as $row) {
                            $h = Carbon::parse($row->created_at)->timezone(config('app.timezone'))->startOfHour();
                            $key = $h->format('Y-m-d H');
                            if (array_key_exists($key, $bucketCounts)) {
                                $bucketCounts[$key]++;
                            }
                        }
                    });
            }

            $values = [];
            foreach (array_keys($bucketCounts) as $key) {
                $values[] = $bucketCounts[$key];
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    public function getActivityTrend(string $campaignCode, int $days = 14): array
    {
        return Cache::remember("activity_trend_{$campaignCode}_{$days}", 300, function () use ($campaignCode, $days) {
            $cutoff = now()->subDays($days)->format('Y-m-d');
            $activityData = $this->aggregateSubmissionTotalsByDay($campaignCode, $cutoff);

            $labels = [];
            $values = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = now()->subDays($i)->format('Y-m-d');
                $labels[] = now()->subDays($i)->format('M d');
                $values[] = $activityData[$d] ?? 0;
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    /** @return array{labels: list<string>, values: list<int>} */
    public function getWeeklyActivityTrend(string $campaignCode): array
    {
        $weekKey = now()->format('o-\WW');

        return Cache::remember("activity_trend_weekly_{$campaignCode}_{$weekKey}", 300, function () use ($campaignCode) {
            $weekStart = now()->copy()->startOfWeek(CarbonInterface::MONDAY);
            $cutoff = $weekStart->format('Y-m-d');
            $activityData = $this->aggregateSubmissionTotalsByDay($campaignCode, $cutoff);

            $labels = [];
            $values = [];
            $today = now()->copy()->startOfDay();

            for ($d = $weekStart->copy(); $d->lte($today); $d->addDay()) {
                $key = $d->format('Y-m-d');
                $labels[] = $d->format('D M j');
                $values[] = $activityData[$key] ?? 0;
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    /** @return array{labels: list<string>, values: list<int>} */
    public function getMonthlyActivityTrend(string $campaignCode): array
    {
        $ym = sprintf('%04d_%02d', now()->year, now()->month);

        return Cache::remember("activity_trend_monthly_{$campaignCode}_{$ym}", 300, function () use ($campaignCode) {
            $monthStart = now()->copy()->startOfMonth()->format('Y-m-d');
            $activityData = $this->aggregateSubmissionTotalsByDay($campaignCode, $monthStart);

            $labels = [];
            $values = [];
            $daysInPartialMonth = now()->day;

            for ($i = 0; $i < $daysInPartialMonth; $i++) {
                $cursor = now()->copy()->startOfMonth()->addDays($i);
                $d = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('M d');
                $values[] = $activityData[$d] ?? 0;
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    /**
     * Month-to-date: submissions (form tables), sale dispositions, optional sale amounts from lead JSON.
     *
     * @return list<array{agent: string, submissions: int, sales_count: int, sales_amount: float}>
     */
    public function getAgentLeaderboard(string $campaignCode, ?int $limit = null): array
    {
        $limit ??= (int) config('dashboard.agent_leaderboard_limit', 25);
        $limit = max(1, $limit);
        $ym = now()->format('Y-m');

        return Cache::remember("agent_leaderboard_{$campaignCode}_{$ym}_{$limit}", 300, function () use ($campaignCode, $limit) {
            $monthStart = now()->copy()->startOfMonth()->toDateString();
            $today = now()->toDateString();

            $submissionCounts = $this->getSubmissionCountsByAgentInDateRange($campaignCode, $monthStart, $today);

            $saleCodes = config('dashboard.sale_disposition_codes', ['SALE']);
            $saleCodes = array_values(array_filter($saleCodes, static fn ($c) => is_string($c) && $c !== ''));

            $salesCounts = [];
            $salesAmounts = [];
            $systemCodes = $this->reportSystemDispositionCodes();
            $markedSaleFields = $this->resolveMarkedSaleFields($campaignCode);

            $salesConfig = $this->dashboardLayoutService->getForCampaign($campaignCode)['sales'] ?? null;
            $resolvedSalesRules = $this->dashboardSalesRuleService->resolveForCampaign($campaignCode, $salesConfig);

            if ($resolvedSalesRules['mode'] === DashboardSalesRuleService::MODE_CUSTOM) {
                $fieldDrivenSales = $this->getCustomSalesByAgentInDateRange(
                    $resolvedSalesRules['forms'],
                    $monthStart,
                    $today,
                );
                $salesCounts = $fieldDrivenSales['counts'];
                $salesAmounts = $fieldDrivenSales['amounts'];
            } elseif ($markedSaleFields['configured']) {
                $fieldDrivenSales = $this->getFieldDrivenSalesByAgentInDateRange(
                    $markedSaleFields['fields'],
                    $monthStart,
                    $today,
                );
                $salesCounts = $fieldDrivenSales['counts'];
                $salesAmounts = $fieldDrivenSales['amounts'];
            } elseif (Schema::hasTable('campaign_disposition_records') && $saleCodes !== []) {
                $query = DB::table('campaign_disposition_records')
                    ->where('campaign_code', $campaignCode)
                    ->whereIn('disposition_code', $saleCodes)
                    ->whereNotNull('called_at')
                    ->whereDate('called_at', '>=', $monthStart)
                    ->whereDate('called_at', '<=', $today)
                    ->whereNotNull('agent')
                    ->where('agent', '!=', '');

                if ($systemCodes !== []) {
                    $query->whereNotIn('disposition_code', $systemCodes);
                }

                $rows = $query->select(['agent', 'lead_data_json'])->get();

                foreach ($rows as $row) {
                    $agent = (string) $row->agent;
                    $salesCounts[$agent] = ($salesCounts[$agent] ?? 0) + 1;
                    $salesAmounts[$agent] = ($salesAmounts[$agent] ?? 0.0) + $this->sumSaleAmountFromLeadJson($row->lead_data_json);
                }
            }

            $agents = array_unique(array_merge(
                array_keys($submissionCounts),
                array_keys($salesCounts),
            ));

            $ranked = [];
            foreach ($agents as $agent) {
                $ranked[] = [
                    'agent' => $agent,
                    'submissions' => $submissionCounts[$agent] ?? 0,
                    'sales_count' => $salesCounts[$agent] ?? 0,
                    'sales_amount' => round($salesAmounts[$agent] ?? 0.0, 2),
                ];
            }

            usort($ranked, static function (array $a, array $b): int {
                if ($a['sales_amount'] != $b['sales_amount']) {
                    return $b['sales_amount'] <=> $a['sales_amount'];
                }
                if ($a['sales_count'] !== $b['sales_count']) {
                    return $b['sales_count'] <=> $a['sales_count'];
                }

                return strcmp($a['agent'], $b['agent']);
            });

            return array_slice($ranked, 0, $limit);
        });
    }

    /**
     * @return array{calls: int, sales: int, sales_amount: float, top_agent: string|null, top_agent_calls: int, top_agent_sales: int, top_agent_sales_amount: float}
     */
    public function getKpisForCampaign(string $campaignCode): array
    {
        $callHours = (int) config('dashboard.kpi_window_hours', 9);
        $salesHours = (int) config('dashboard.sales_kpi_window_hours', 24);

        return Cache::remember("dashboard_kpis_{$campaignCode}_{$callHours}_{$salesHours}", 60, function () use ($campaignCode, $callHours, $salesHours) {
            $empty = [
                'calls' => 0,
                'sales' => 0,
                'sales_amount' => 0.0,
                'top_agent' => null,
                'top_agent_calls' => 0,
                'top_agent_sales' => 0,
                'top_agent_sales_amount' => 0.0,
            ];

            $callSince = now()->subHours($callHours);
            $salesSince = now()->subHours($salesHours);
            $systemCodes = $this->reportSystemDispositionCodes();
            $hasDispositionRecords = Schema::hasTable('campaign_disposition_records');

            $calls = 0;
            if ($hasDispositionRecords) {
                $callsQuery = DB::table('campaign_disposition_records')
                    ->where('campaign_code', $campaignCode)
                    ->whereNotNull('called_at')
                    ->where('called_at', '>=', $callSince);

                if ($systemCodes !== []) {
                    $callsQuery->whereNotIn('disposition_code', $systemCodes);
                }

                $calls = (int) $callsQuery->count();
            }

            /** @var list<string> $saleCodes */
            $saleCodes = config('dashboard.sale_disposition_codes', ['SALE']);
            $saleCodes = array_values(array_filter($saleCodes, static fn ($c) => is_string($c) && $c !== ''));

            $markedSaleFields = $this->resolveMarkedSaleFields($campaignCode);
            $sales = 0;
            $salesAmount = 0.0;
            $topAgent = null;
            $topAgentCalls = 0;
            $topAgentSales = 0;
            $topAgentSalesAmount = 0.0;
            $salesConfig = $this->dashboardLayoutService->getForCampaign($campaignCode)['sales'] ?? null;
            $resolvedSalesRules = $this->dashboardSalesRuleService->resolveForCampaign($campaignCode, $salesConfig);

            if ($resolvedSalesRules['mode'] === DashboardSalesRuleService::MODE_CUSTOM) {
                $fieldDrivenSales = $this->getCustomSalesByAgentSince($resolvedSalesRules['forms'], $salesSince);
                $sales = $fieldDrivenSales['count'];
                $salesAmount = $fieldDrivenSales['amount'];

                $agents = array_keys($fieldDrivenSales['counts']);
                usort($agents, static function (string $a, string $b) use ($fieldDrivenSales): int {
                    if ($fieldDrivenSales['counts'][$a] !== $fieldDrivenSales['counts'][$b]) {
                        return $fieldDrivenSales['counts'][$b] <=> $fieldDrivenSales['counts'][$a];
                    }
                    if ($fieldDrivenSales['amounts'][$a] != $fieldDrivenSales['amounts'][$b]) {
                        return $fieldDrivenSales['amounts'][$b] <=> $fieldDrivenSales['amounts'][$a];
                    }

                    return strcmp($a, $b);
                });

                $topAgent = $agents[0] ?? null;
                $topAgentSales = $topAgent === null ? 0 : $fieldDrivenSales['counts'][$topAgent];
                $topAgentSalesAmount = $topAgent === null ? 0.0 : $fieldDrivenSales['amounts'][$topAgent];
            } elseif ($markedSaleFields['configured']) {
                $fieldDrivenSales = $this->getFieldDrivenSalesByAgentSince($markedSaleFields['fields'], $salesSince);
                $sales = $fieldDrivenSales['count'];
                $salesAmount = $fieldDrivenSales['amount'];

                $agents = array_keys($fieldDrivenSales['counts']);
                usort($agents, static function (string $a, string $b) use ($fieldDrivenSales): int {
                    if ($fieldDrivenSales['counts'][$a] !== $fieldDrivenSales['counts'][$b]) {
                        return $fieldDrivenSales['counts'][$b] <=> $fieldDrivenSales['counts'][$a];
                    }
                    if ($fieldDrivenSales['amounts'][$a] != $fieldDrivenSales['amounts'][$b]) {
                        return $fieldDrivenSales['amounts'][$b] <=> $fieldDrivenSales['amounts'][$a];
                    }

                    return strcmp($a, $b);
                });

                $topAgent = $agents[0] ?? null;
                $topAgentSales = $topAgent === null ? 0 : $fieldDrivenSales['counts'][$topAgent];
                $topAgentSalesAmount = $topAgent === null ? 0.0 : $fieldDrivenSales['amounts'][$topAgent];
            } elseif ($hasDispositionRecords && $saleCodes !== []) {
                $salesQuery = DB::table('campaign_disposition_records')
                    ->where('campaign_code', $campaignCode)
                    ->whereNotNull('called_at')
                    ->where('called_at', '>=', $salesSince)
                    ->whereIn('disposition_code', $saleCodes);

                if ($systemCodes !== []) {
                    $salesQuery->whereNotIn('disposition_code', $systemCodes);
                }

                $salesQuery
                    ->select(['id', 'lead_data_json'])
                    ->orderBy('id')
                    ->chunk(1000, function ($rows) use (&$sales, &$salesAmount): void {
                        foreach ($rows as $row) {
                            $sales++;
                            $salesAmount += $this->sumSaleAmountFromLeadJson($row->lead_data_json);
                        }
                    });
            }

            if ($resolvedSalesRules['mode'] === DashboardSalesRuleService::MODE_LEGACY
                && ! $markedSaleFields['configured']
                && $hasDispositionRecords) {
                $topQuery = DB::table('campaign_disposition_records')
                    ->where('campaign_code', $campaignCode)
                    ->whereNotNull('called_at')
                    ->where('called_at', '>=', $salesSince)
                    ->whereNotNull('agent')
                    ->where('agent', '!=', '');

                if ($systemCodes !== []) {
                    $topQuery->whereNotIn('disposition_code', $systemCodes);
                }

                $top = $topQuery
                    ->select('agent', DB::raw('COUNT(*) as total'))
                    ->groupBy('agent')
                    ->orderByDesc('total')
                    ->orderBy('agent')
                    ->first();

                $topAgent = $top->agent ?? null;
                $topAgentCalls = isset($top->total) ? (int) $top->total : 0;
            }

            return [
                'calls' => $calls,
                'sales' => $sales,
                'sales_amount' => round($salesAmount, 2),
                'top_agent' => $topAgent,
                'top_agent_calls' => $topAgentCalls,
                'top_agent_sales' => $topAgentSales,
                'top_agent_sales_amount' => round($topAgentSalesAmount, 2),
            ];
        });
    }

    /**
     * Form-field sales for an explicit date and time interval.
     *
     * Disposition records and lead JSON are intentionally excluded. A sale is a
     * submission with at least one numeric value in a form field marked as a sale amount.
     *
     * @return array{sales: int, sales_amount: float, top_agent: string|null, top_agent_sales: int, top_agent_sales_amount: float, sales_by_form: list<array{form_code: string, form_name: string, sales: int, sales_amount: float}>, agent_leaderboard: list<array{agent: string, sales_count: int, sales_amount: float}>}
     */
    public function getSalesKpisForCampaign(string $campaignCode, Carbon $from, Carbon $until): array
    {
        $empty = [
            'sales' => 0,
            'sales_amount' => 0.0,
            'top_agent' => null,
            'top_agent_sales' => 0,
            'top_agent_sales_amount' => 0.0,
            'sales_by_form' => [],
            'agent_leaderboard' => [],
        ];

        if ($until->lte($from)) {
            return $empty;
        }

        $salesConfig = $this->dashboardLayoutService->getForCampaign($campaignCode)['sales'] ?? null;
        $resolvedSalesRules = $this->dashboardSalesRuleService->resolveForCampaign($campaignCode, $salesConfig);

        if ($resolvedSalesRules['mode'] === DashboardSalesRuleService::MODE_CUSTOM) {
            $fieldDrivenSales = $this->getCustomSalesByAgentInRange(
                $resolvedSalesRules['forms'],
                $from,
                $until,
            );
        } else {
            $markedSaleFields = $this->resolveMarkedSaleFields($campaignCode);
            if (! $markedSaleFields['configured']) {
                return $empty;
            }

            $fieldDrivenSales = $this->getFieldDrivenSalesByAgentInRange(
                $markedSaleFields['fields'],
                $markedSaleFields['forms'],
                $from,
                $until,
            );
        }

        $agentLeaderboard = $this->buildSalesLeaderboard(
            $fieldDrivenSales['counts'],
            $fieldDrivenSales['amounts'],
        );
        $topAgent = $agentLeaderboard[0]['agent'] ?? null;

        return [
            'sales' => $fieldDrivenSales['count'],
            'sales_amount' => round($fieldDrivenSales['amount'], 2),
            'top_agent' => $topAgent,
            'top_agent_sales' => $topAgent === null ? 0 : $fieldDrivenSales['counts'][$topAgent],
            'top_agent_sales_amount' => $topAgent === null ? 0.0 : round($fieldDrivenSales['amounts'][$topAgent], 2),
            'sales_by_form' => $fieldDrivenSales['sales_by_form'],
            'agent_leaderboard' => $agentLeaderboard,
        ];
    }

    /**
     * Daily and month-to-date submission counts and marked sale amounts grouped by agent.
     *
     * @return array{
     *     date: string,
     *     forms: list<array{code: string, name: string}>,
     *     daily: list<array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}>,
     *     month_to_date: list<array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}>,
     *     totals: array{daily: array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}, month_to_date: array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}}
     * }
     */
    public function getDailyCampaignReport(string $campaignCode, Carbon $businessDate): array
    {
        $date = $businessDate->toDateString();
        $cacheKey = 'daily_campaign_report_'.$campaignCode.'_'.$date;

        return Cache::remember($cacheKey, 60, function () use ($campaignCode, $businessDate, $date): array {
            $salesConfig = $this->dashboardLayoutService->getForCampaign($campaignCode)['sales'] ?? null;
            $resolvedSalesRules = $this->dashboardSalesRuleService->resolveForCampaign($campaignCode, $salesConfig);
            if ($resolvedSalesRules['mode'] === DashboardSalesRuleService::MODE_CUSTOM) {
                return $this->buildCustomDailyCampaignReport($resolvedSalesRules['forms'], $businessDate, $date);
            }

            $forms = $this->resolveReportForms($campaignCode);
            $formColumns = array_map(static fn (array $form): array => [
                'code' => $form['code'],
                'name' => $form['name'],
            ], $forms);
            $emptyTotals = $this->emptyCampaignReportTotals($formColumns);

            if ($forms === []) {
                return [
                    'date' => $date,
                    'forms' => $formColumns,
                    'daily' => [],
                    'month_to_date' => [],
                    'totals' => [
                        'daily' => $emptyTotals,
                        'month_to_date' => $emptyTotals,
                    ],
                ];
            }

            $monthStart = $businessDate->copy()->startOfMonth()->toDateString();
            $dailyRows = [];
            $monthToDateRows = [];
            $dailyTotals = $this->emptyCampaignReportTotals($formColumns);
            $monthToDateTotals = $this->emptyCampaignReportTotals($formColumns);

            foreach ($forms as $form) {
                if (! Schema::hasColumn($form['table'], 'date') || ! Schema::hasColumn($form['table'], 'agent')) {
                    continue;
                }

                $select = array_merge(['id', 'date', 'agent'], $form['amount_fields']);
                DB::table($form['table'])
                    ->whereBetween('date', [$monthStart, $date])
                    ->select(array_values(array_unique($select)))
                    ->orderBy('id')
                    ->chunk(1000, function ($rows) use (
                        &$dailyRows,
                        &$monthToDateRows,
                        &$dailyTotals,
                        &$monthToDateTotals,
                        $form,
                        $formColumns,
                        $date,
                    ): void {
                        foreach ($rows as $row) {
                            $isDaily = (string) $row->date === $date;
                            $this->addCampaignReportEntry(
                                $monthToDateRows,
                                $monthToDateTotals,
                                $form,
                                $formColumns,
                                $row,
                            );
                            if ($isDaily) {
                                $this->addCampaignReportEntry(
                                    $dailyRows,
                                    $dailyTotals,
                                    $form,
                                    $formColumns,
                                    $row,
                                );
                            }
                        }
                    });
            }

            return [
                'date' => $date,
                'forms' => $formColumns,
                'daily' => $this->normalizeCampaignReportRows($dailyRows),
                'month_to_date' => $this->normalizeCampaignReportRows($monthToDateRows),
                'totals' => [
                    'daily' => $this->normalizeCampaignReportTotals($dailyTotals),
                    'month_to_date' => $this->normalizeCampaignReportTotals($monthToDateTotals),
                ],
            ];
        });
    }

    /**
     * @param  list<array{form_code: string, form_name: string, table: string, amount_field: string|null, trigger: string, conditions: list<array{field_name: string, accepted_values: list<string>}>}>  $forms
     * @return array{date: string, forms: list<array{code: string, name: string}>, daily: list<array<string, mixed>>, month_to_date: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    private function buildCustomDailyCampaignReport(array $forms, Carbon $businessDate, string $date): array
    {
        $formColumns = array_map(static fn (array $form): array => [
            'code' => $form['form_code'],
            'name' => $form['form_name'],
        ], $forms);
        $emptyTotals = $this->emptyCampaignReportTotals($formColumns);

        if ($forms === []) {
            return [
                'date' => $date,
                'forms' => $formColumns,
                'daily' => [],
                'month_to_date' => [],
                'totals' => ['daily' => $emptyTotals, 'month_to_date' => $emptyTotals],
            ];
        }

        $monthStart = $businessDate->copy()->startOfMonth()->toDateString();
        $dailyRows = [];
        $monthToDateRows = [];
        $dailyTotals = $this->emptyCampaignReportTotals($formColumns);
        $monthToDateTotals = $this->emptyCampaignReportTotals($formColumns);

        foreach ($forms as $form) {
            if (! Schema::hasColumn($form['table'], 'date') || ! Schema::hasColumn($form['table'], 'agent')) {
                continue;
            }

            $conditionFields = array_column($form['conditions'], 'field_name');
            $select = array_values(array_unique(array_merge(
                ['id', 'date', 'agent'],
                $conditionFields,
                $form['amount_field'] === null ? [] : [$form['amount_field']],
            )));
            DB::table($form['table'])
                ->whereBetween('date', [$monthStart, $date])
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (
                    &$dailyRows,
                    &$monthToDateRows,
                    &$dailyTotals,
                    &$monthToDateTotals,
                    $form,
                    $formColumns,
                    $date,
                ): void {
                    foreach ($rows as $row) {
                        if (! $this->matchesCustomConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                            continue;
                        }

                        $agent = trim((string) ($row->agent ?? ''));
                        if ($agent === '') {
                            continue;
                        }

                        $amount = $this->customSaleAmount($row, $form['amount_field']);
                        $this->recordCampaignReportEntry(
                            $monthToDateRows,
                            $monthToDateTotals,
                            $formColumns,
                            $form['form_code'],
                            $agent,
                            $amount,
                        );
                        if ((string) $row->date === $date) {
                            $this->recordCampaignReportEntry(
                                $dailyRows,
                                $dailyTotals,
                                $formColumns,
                                $form['form_code'],
                                $agent,
                                $amount,
                            );
                        }
                    }
                });
        }

        return [
            'date' => $date,
            'forms' => $formColumns,
            'daily' => $this->normalizeCampaignReportRows($dailyRows),
            'month_to_date' => $this->normalizeCampaignReportRows($monthToDateRows),
            'totals' => [
                'daily' => $this->normalizeCampaignReportTotals($dailyTotals),
                'month_to_date' => $this->normalizeCampaignReportTotals($monthToDateTotals),
            ],
        ];
    }

    public function getTopAgents(string $campaignCode, int $limit = 10): array
    {
        return Cache::remember("top_agents_{$campaignCode}_{$limit}", 300, function () use ($campaignCode, $limit) {
            $tables = $this->resolveAllowedTables($campaignCode);
            if (empty($tables)) {
                return ['labels' => [], 'values' => []];
            }

            $queries = [];
            foreach ($tables as $t) {
                $queries[] = DB::table($t)->select('agent')->whereNotNull('agent')->where('agent', '!=', '');
            }

            $union = array_shift($queries);
            foreach ($queries as $q) {
                $union = $union->unionAll($q);
            }

            $rows = DB::table(DB::raw("({$union->toSql()}) as combined"))
                ->mergeBindings($union)
                ->select('agent', DB::raw('COUNT(*) as total'))
                ->groupBy('agent')
                ->orderByDesc('total')
                ->limit($limit)
                ->get();

            return [
                'labels' => $rows->pluck('agent')->all(),
                'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ];
        });
    }

    public function invalidate(string $campaignCode, int $days = 14): void
    {
        Cache::forget("activity_trend_{$campaignCode}_{$days}");
        Cache::forget("top_agents_{$campaignCode}_10");

        Cache::forget("activity_trend_24h_{$campaignCode}");

        $ym = sprintf('%04d_%02d', now()->year, now()->month);
        Cache::forget("activity_trend_monthly_{$campaignCode}_{$ym}");

        $wk = now()->format('o-\WW');
        Cache::forget("activity_trend_weekly_{$campaignCode}_{$wk}");

        $callHours = (int) config('dashboard.kpi_window_hours', 9);
        $salesHours = (int) config('dashboard.sales_kpi_window_hours', 24);
        Cache::forget("dashboard_kpis_{$campaignCode}_{$callHours}_{$salesHours}");

        $limit = (int) config('dashboard.agent_leaderboard_limit', 25);
        Cache::forget('agent_leaderboard_'.$campaignCode.'_'.now()->format('Y-m').'_'.$limit);

        Cache::forget('daily_campaign_report_'.$campaignCode.'_'.now()->toDateString());
    }

    /**
     * @return list<array{code: string, name: string, table: string, amount_fields: list<string>}>
     */
    private function resolveReportForms(string $campaignCode): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $campaignForms = $campaigns[$campaignCode]['forms'] ?? [];
        $allowedTables = $this->campaignRepository->getAllFormTableNames();
        $markedFields = FormField::query()
            ->where('campaign_code', $campaignCode)
            ->where('field_type', 'number')
            ->where('is_sale_amount', true)
            ->get(['form_type', 'field_name']);
        $fieldsByForm = [];

        foreach ($markedFields as $field) {
            $fieldsByForm[(string) $field->form_type][] = (string) $field->field_name;
        }

        $forms = [];
        foreach ($campaignForms as $formCode => $formConfig) {
            $table = (string) ($formConfig['table'] ?? $formConfig['table_name'] ?? '');
            if ($table === ''
                || ! in_array($table, $allowedTables, true)
                || ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'date')
                || ! Schema::hasColumn($table, 'agent')) {
                continue;
            }

            $amountFields = array_values(array_unique(array_filter(
                $fieldsByForm[(string) $formCode] ?? [],
                static fn (string $fieldName): bool => Schema::hasColumn($table, $fieldName),
            )));
            $forms[] = [
                'code' => (string) $formCode,
                'name' => (string) ($formConfig['name'] ?? $formCode),
                'table' => $table,
                'amount_fields' => $amountFields,
            ];
        }

        return $forms;
    }

    /**
     * @param  list<array{code: string, name: string}>  $forms
     * @return array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}
     */
    private function emptyCampaignReportTotals(array $forms): array
    {
        return [
            'counts' => array_fill_keys(array_column($forms, 'code'), 0),
            'amounts' => array_fill_keys(array_column($forms, 'code'), 0.0),
            'total_count' => 0,
            'total_amount' => 0.0,
        ];
    }

    /**
     * @param  list<array{code: string, name: string}>  $forms
     * @return array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}
     */
    private function emptyCampaignReportRow(array $forms): array
    {
        $totals = $this->emptyCampaignReportTotals($forms);

        return [
            'agent' => '',
            ...$totals,
        ];
    }

    /**
     * @param  array<string, array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}>  $rows
     * @param  array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}  $totals
     * @param  array{code: string, name: string, table: string, amount_fields: list<string>}  $form
     * @param  list<array{code: string, name: string}>  $formColumns
     */
    private function addCampaignReportEntry(
        array &$rows,
        array &$totals,
        array $form,
        array $formColumns,
        object $row,
    ): void {
        $agent = trim((string) ($row->agent ?? ''));
        if ($agent === '') {
            return;
        }

        $amount = $this->sumMarkedSaleValues($row, $form['amount_fields']) ?? 0.0;
        $this->recordCampaignReportEntry($rows, $totals, $formColumns, (string) $form['code'], $agent, $amount);
    }

    /**
     * @param  array<string, array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}>  $rows
     * @param  array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}  $totals
     * @param  list<array{code: string, name: string}>  $formColumns
     */
    private function recordCampaignReportEntry(
        array &$rows,
        array &$totals,
        array $formColumns,
        string $formCode,
        string $agent,
        float $amount,
    ): void {
        if (! isset($rows[$agent])) {
            $reportRow = $this->emptyCampaignReportRow($formColumns);
            $reportRow['agent'] = $agent;
            $rows[$agent] = $reportRow;
        }

        $rows[$agent]['counts'][$formCode]++;
        $rows[$agent]['amounts'][$formCode] += $amount;
        $rows[$agent]['total_count']++;
        $rows[$agent]['total_amount'] += $amount;
        $totals['counts'][$formCode]++;
        $totals['amounts'][$formCode] += $amount;
        $totals['total_count']++;
        $totals['total_amount'] += $amount;
    }

    /**
     * @param  array<string, array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}>  $rows
     * @return list<array{agent: string, counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}>
     */
    private function normalizeCampaignReportRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['total_amount'] = round($row['total_amount'], 2);
            foreach ($row['amounts'] as &$amount) {
                $amount = round($amount, 2);
            }
        }
        unset($row, $amount);
        ksort($rows, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($rows);
    }

    /**
     * @param  array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}  $totals
     * @return array{counts: array<string, int>, amounts: array<string, float>, total_count: int, total_amount: float}
     */
    private function normalizeCampaignReportTotals(array $totals): array
    {
        foreach ($totals['amounts'] as &$amount) {
            $amount = round($amount, 2);
        }
        unset($amount);
        $totals['total_amount'] = round($totals['total_amount'], 2);

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function getSubmissionCountsByAgentInDateRange(string $campaignCode, string $fromYmd, string $toYmd): array
    {
        $tables = $this->resolveAllowedTables($campaignCode);
        $counts = [];
        foreach ($tables as $t) {
            $rows = DB::table($t)
                ->select('agent', DB::raw('COUNT(*) as c'))
                ->whereBetween('date', [$fromYmd, $toYmd])
                ->whereNotNull('agent')
                ->where('agent', '!=', '')
                ->groupBy('agent')
                ->get();
            foreach ($rows as $row) {
                $agent = (string) $row->agent;
                $counts[$agent] = ($counts[$agent] ?? 0) + (int) $row->c;
            }
        }

        return $counts;
    }

    private function sumSaleAmountFromLeadJson(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($data)) {
            return 0.0;
        }
        /** @var list<string> $keys */
        $keys = config('dashboard.sale_amount_json_keys', []);
        foreach ($keys as $k) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if (isset($data[$k]) && is_numeric($data[$k])) {
                return (float) $data[$k];
            }
        }

        return 0.0;
    }

    /**
     * @return array{configured: bool, fields: array<string, list<string>>, forms: array<string, array{form_code: string, form_name: string}>}
     */
    private function resolveMarkedSaleFields(string $campaignCode): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $forms = $campaigns[$campaignCode]['forms'] ?? [];
        $markedFields = FormField::query()
            ->where('campaign_code', $campaignCode)
            ->where('field_type', 'number')
            ->where('is_sale_amount', true)
            ->get(['form_type', 'field_name']);
        $allowedTables = $this->campaignRepository->getAllFormTableNames();

        $fieldsByTable = [];
        $formsByTable = [];
        foreach ($markedFields as $field) {
            $formCode = (string) $field->form_type;
            $formConfig = $forms[$formCode] ?? [];
            $tableName = (string) ($formConfig['table'] ?? '');
            $fieldName = (string) $field->field_name;

            if ($tableName === '' || $fieldName === '') {
                continue;
            }
            if (! in_array($tableName, $allowedTables, true)) {
                continue;
            }
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $fieldName)) {
                continue;
            }

            $fieldsByTable[$tableName] ??= [];
            $fieldsByTable[$tableName][] = $fieldName;
            $formsByTable[$tableName] = [
                'form_code' => $formCode,
                'form_name' => (string) ($formConfig['name'] ?? $formCode),
            ];
        }

        foreach ($fieldsByTable as $tableName => $fieldNames) {
            $fieldsByTable[$tableName] = array_values(array_unique($fieldNames));
        }

        return [
            'configured' => $fieldsByTable !== [],
            'fields' => $fieldsByTable,
            'forms' => $formsByTable,
        ];
    }

    /**
     * @param  array<string, list<string>>  $fieldsByTable
     * @return array{count: int, amount: float, counts: array<string, int>, amounts: array<string, float>}
     */
    private function getFieldDrivenSalesByAgentSince(array $fieldsByTable, Carbon $since): array
    {
        $sales = 0;
        $amount = 0.0;
        $counts = [];
        $amounts = [];
        foreach ($fieldsByTable as $tableName => $fieldNames) {
            if (! Schema::hasColumn($tableName, 'created_at')) {
                continue;
            }

            $hasAgent = Schema::hasColumn($tableName, 'agent');
            $select = array_merge(['id'], $fieldNames);
            if ($hasAgent) {
                $select[] = 'agent';
            }

            DB::table($tableName)
                ->where('created_at', '>=', $since)
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, $fieldNames, $hasAgent): void {
                    foreach ($rows as $row) {
                        $saleAmount = $this->sumMarkedSaleValues($row, $fieldNames);
                        if ($saleAmount !== null) {
                            $sales++;
                            $amount += $saleAmount;

                            if (! $hasAgent) {
                                continue;
                            }

                            $agent = trim((string) ($row->agent ?? ''));
                            if ($agent === '') {
                                continue;
                            }

                            $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                            $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                        }
                    }
                });
        }

        return [
            'count' => $sales,
            'amount' => $amount,
            'counts' => $counts,
            'amounts' => $amounts,
        ];
    }

    /**
     * @param  array<string, list<string>>  $fieldsByTable
     * @param  array<string, array{form_code: string, form_name: string}>  $formsByTable
     * @return array{count: int, amount: float, counts: array<string, int>, amounts: array<string, float>, sales_by_form: list<array{form_code: string, form_name: string, sales: int, sales_amount: float}>}
     */
    private function getFieldDrivenSalesByAgentInRange(
        array $fieldsByTable,
        array $formsByTable,
        Carbon $from,
        Carbon $until,
    ): array {
        $sales = 0;
        $amount = 0.0;
        $counts = [];
        $amounts = [];
        $salesByForm = [];

        foreach ($fieldsByTable as $tableName => $fieldNames) {
            $form = $formsByTable[$tableName] ?? [
                'form_code' => $tableName,
                'form_name' => $tableName,
            ];
            $salesByForm[$tableName] = [
                'form_code' => $form['form_code'],
                'form_name' => $form['form_name'],
                'sales' => 0,
                'sales_amount' => 0.0,
            ];

            if (! Schema::hasColumn($tableName, 'created_at')) {
                continue;
            }

            $hasAgent = Schema::hasColumn($tableName, 'agent');
            $select = array_merge(['id'], $fieldNames);
            if ($hasAgent) {
                $select[] = 'agent';
            }

            DB::table($tableName)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $until)
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, &$salesByForm, $tableName, $fieldNames, $hasAgent): void {
                    foreach ($rows as $row) {
                        $saleAmount = $this->sumMarkedSaleValues($row, $fieldNames);
                        if ($saleAmount === null) {
                            continue;
                        }

                        $sales++;
                        $amount += $saleAmount;
                        $salesByForm[$tableName]['sales']++;
                        $salesByForm[$tableName]['sales_amount'] += $saleAmount;

                        if (! $hasAgent) {
                            continue;
                        }

                        $agent = trim((string) ($row->agent ?? ''));
                        if ($agent === '') {
                            continue;
                        }

                        $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                        $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                    }
                });
        }

        foreach ($salesByForm as &$form) {
            $form['sales_amount'] = round($form['sales_amount'], 2);
        }
        unset($form);

        return [
            'count' => $sales,
            'amount' => $amount,
            'counts' => $counts,
            'amounts' => $amounts,
            'sales_by_form' => array_values($salesByForm),
        ];
    }

    /**
     * @param  list<array{form_code: string, form_name: string, table: string, amount_field: string|null, trigger: string, conditions: list<array{field_name: string, accepted_values: list<string>}>}>  $forms
     * @return array{count: int, amount: float, counts: array<string, int>, amounts: array<string, float>, sales_by_form: list<array{form_code: string, form_name: string, sales: int, sales_amount: float}>}
     */
    private function getCustomSalesByAgentInRange(array $forms, Carbon $from, Carbon $until): array
    {
        $sales = 0;
        $amount = 0.0;
        $counts = [];
        $amounts = [];
        $salesByForm = [];

        foreach ($forms as $form) {
            $tableName = $form['table'];
            $salesByForm[$tableName] = [
                'form_code' => $form['form_code'],
                'form_name' => $form['form_name'],
                'sales' => 0,
                'sales_amount' => 0.0,
            ];

            if (! Schema::hasColumn($tableName, 'created_at')) {
                continue;
            }

            $conditionFields = array_column(
                array_map(static fn (array $condition): array => [
                    'field_name' => $condition['field_name'],
                ], $form['conditions']),
                'field_name',
            );
            $select = array_values(array_unique(array_merge(
                ['id'],
                $conditionFields,
                $form['amount_field'] === null ? [] : [$form['amount_field']],
                Schema::hasColumn($tableName, 'agent') ? ['agent'] : [],
            )));

            DB::table($tableName)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $until)
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, &$salesByForm, $tableName, $form): void {
                    foreach ($rows as $row) {
                        if (! $this->matchesCustomConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                            continue;
                        }

                        $saleAmount = $this->customSaleAmount($row, $form['amount_field']);
                        $sales++;
                        $amount += $saleAmount;
                        $salesByForm[$tableName]['sales']++;
                        $salesByForm[$tableName]['sales_amount'] += $saleAmount;

                        $agent = trim((string) ($row->agent ?? ''));
                        if ($agent === '') {
                            continue;
                        }

                        $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                        $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                    }
                });
        }

        foreach ($salesByForm as &$form) {
            $form['sales_amount'] = round($form['sales_amount'], 2);
        }
        unset($form);

        return [
            'count' => $sales,
            'amount' => $amount,
            'counts' => $counts,
            'amounts' => $amounts,
            'sales_by_form' => array_values($salesByForm),
        ];
    }

    /**
     * @param  list<array{form_code: string, form_name: string, table: string, amount_field: string|null, trigger: string, conditions: list<array{field_name: string, accepted_values: list<string>}>}>  $forms
     * @return array{count: int, amount: float, counts: array<string, int>, amounts: array<string, float>}
     */
    private function getCustomSalesByAgentSince(array $forms, Carbon $since): array
    {
        $sales = 0;
        $amount = 0.0;
        $counts = [];
        $amounts = [];

        foreach ($forms as $form) {
            if (! Schema::hasColumn($form['table'], 'created_at')) {
                continue;
            }

            $conditionFields = array_column($form['conditions'], 'field_name');
            $select = array_values(array_unique(array_merge(
                ['id', ...$conditionFields],
                $form['amount_field'] === null ? [] : [$form['amount_field']],
                Schema::hasColumn($form['table'], 'agent') ? ['agent'] : [],
            )));
            DB::table($form['table'])
                ->where('created_at', '>=', $since)
                ->where('created_at', '<=', now())
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, $form): void {
                    foreach ($rows as $row) {
                        if (! $this->matchesCustomConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                            continue;
                        }

                        $saleAmount = $this->customSaleAmount($row, $form['amount_field']);
                        $sales++;
                        $amount += $saleAmount;
                        $agent = trim((string) ($row->agent ?? ''));
                        if ($agent === '') {
                            continue;
                        }

                        $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                        $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                    }
                });
        }

        return [
            'count' => $sales,
            'amount' => $amount,
            'counts' => $counts,
            'amounts' => $amounts,
        ];
    }

    /**
     * @param  list<array{form_code: string, form_name: string, table: string, amount_field: string|null, trigger: string, conditions: list<array{field_name: string, accepted_values: list<string>}>}>  $forms
     * @return array{count: int, amount: float, counts: array<string, int>, amounts: array<string, float>}
     */
    private function getCustomSalesByAgentInDateRange(array $forms, string $fromYmd, string $toYmd): array
    {
        $sales = 0;
        $amount = 0.0;
        $counts = [];
        $amounts = [];

        foreach ($forms as $form) {
            if (! Schema::hasColumn($form['table'], 'date')) {
                continue;
            }

            $conditionFields = array_column($form['conditions'], 'field_name');
            $select = array_values(array_unique(array_merge(
                ['id', ...$conditionFields],
                $form['amount_field'] === null ? [] : [$form['amount_field']],
                Schema::hasColumn($form['table'], 'agent') ? ['agent'] : [],
            )));
            DB::table($form['table'])
                ->whereBetween('date', [$fromYmd, $toYmd])
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, $form): void {
                    foreach ($rows as $row) {
                        if (! $this->matchesCustomConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                            continue;
                        }

                        $saleAmount = $this->customSaleAmount($row, $form['amount_field']);
                        $sales++;
                        $amount += $saleAmount;
                        $agent = trim((string) ($row->agent ?? ''));
                        if ($agent === '') {
                            continue;
                        }

                        $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                        $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                    }
                });
        }

        return [
            'count' => $sales,
            'amount' => $amount,
            'counts' => $counts,
            'amounts' => $amounts,
        ];
    }

    /**
     * @param  list<array{field_name: string, accepted_values: list<string>}>  $conditions
     */
    private function matchesCustomConditions(
        object $row,
        array $conditions,
        ?string $markerField = null,
        string $trigger = DashboardSalesRuleService::TRIGGER_TAG,
    ): bool {
        if ($trigger === DashboardSalesRuleService::TRIGGER_FORM) {
            return true;
        }

        if ($trigger === DashboardSalesRuleService::TRIGGER_MARKED_AMOUNT) {
            return $markerField !== null && $this->hasNumericValue($row->{$markerField} ?? null);
        }

        foreach ($conditions as $condition) {
            $value = $row->{$condition['field_name']} ?? null;
            if ($value === null) {
                continue;
            }

            $normalized = $this->normalizeSalesTagValue((string) $value);
            if (in_array($normalized, $condition['accepted_values'], true)) {
                return true;
            }
        }

        return false;
    }

    private function customSaleAmount(object $row, ?string $amountField): float
    {
        if ($amountField === null) {
            return 0.0;
        }

        $value = $row->{$amountField} ?? null;
        if ($value === null || (is_string($value) && trim($value) === '') || ! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    private function hasNumericValue(mixed $value): bool
    {
        return $value !== null
            && (! is_string($value) || trim($value) !== '')
            && is_numeric($value);
    }

    private function normalizeSalesTagValue(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, float>  $amounts
     * @return list<array{agent: string, sales_count: int, sales_amount: float}>
     */
    private function buildSalesLeaderboard(array $counts, array $amounts): array
    {
        $agents = array_unique(array_merge(array_keys($counts), array_keys($amounts)));
        $leaderboard = [];

        foreach ($agents as $agent) {
            $leaderboard[] = [
                'agent' => (string) $agent,
                'sales_count' => $counts[$agent] ?? 0,
                'sales_amount' => round($amounts[$agent] ?? 0.0, 2),
            ];
        }

        usort($leaderboard, static function (array $a, array $b): int {
            if ($a['sales_amount'] != $b['sales_amount']) {
                return $b['sales_amount'] <=> $a['sales_amount'];
            }
            if ($a['sales_count'] !== $b['sales_count']) {
                return $b['sales_count'] <=> $a['sales_count'];
            }

            return strcmp($a['agent'], $b['agent']);
        });

        return $leaderboard;
    }

    /**
     * @param  array<string, list<string>>  $fieldsByTable
     * @return array{counts: array<string, int>, amounts: array<string, float>}
     */
    private function getFieldDrivenSalesByAgentInDateRange(
        array $fieldsByTable,
        string $fromYmd,
        string $toYmd,
    ): array {
        $counts = [];
        $amounts = [];

        foreach ($fieldsByTable as $tableName => $fieldNames) {
            if (! Schema::hasColumn($tableName, 'date') || ! Schema::hasColumn($tableName, 'agent')) {
                continue;
            }

            DB::table($tableName)
                ->select(array_merge(['id', 'agent'], $fieldNames))
                ->whereBetween('date', [$fromYmd, $toYmd])
                ->whereNotNull('agent')
                ->where('agent', '!=', '')
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$counts, &$amounts, $fieldNames): void {
                    foreach ($rows as $row) {
                        $amount = $this->sumMarkedSaleValues($row, $fieldNames);
                        if ($amount === null) {
                            continue;
                        }

                        $agent = (string) $row->agent;
                        $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                        $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $amount;
                    }
                });
        }

        return [
            'counts' => $counts,
            'amounts' => $amounts,
        ];
    }

    /**
     * @param  list<string>  $fieldNames
     */
    private function sumMarkedSaleValues(object $row, array $fieldNames): ?float
    {
        $amount = null;
        foreach ($fieldNames as $fieldName) {
            $value = $row->{$fieldName} ?? null;
            if ($value === null || (is_string($value) && trim($value) === '') || ! is_numeric($value)) {
                continue;
            }

            $amount = ($amount ?? 0.0) + (float) $value;
        }

        return $amount;
    }

    /**
     * Daily form submission totals for allowed tables since $sinceDateYmd (inclusive day).
     *
     * @return array<string, int>
     */
    private function aggregateSubmissionTotalsByDay(string $campaignCode, string $sinceDateYmd): array
    {
        $tables = $this->resolveAllowedTables($campaignCode);
        if (empty($tables)) {
            return [];
        }

        $queries = [];
        foreach ($tables as $t) {
            $queries[] = DB::table($t)
                ->select(DB::raw("'$t' as source, `date`"))
                ->where('date', '>=', $sinceDateYmd);
        }

        $union = array_shift($queries);
        foreach ($queries as $q) {
            $union = $union->unionAll($q);
        }

        $rows = DB::table(DB::raw("({$union->toSql()}) as combined"))
            ->mergeBindings($union)
            ->select(DB::raw('`date`, COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $activityData = [];
        foreach ($rows as $row) {
            $activityData[$row->date] = (int) $row->total;
        }

        return $activityData;
    }

    /** @return list<string> */
    private function resolveAllowedTables(string $campaignCode): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $config = $campaigns[$campaignCode] ?? null;
        if (! $config || empty($config['forms'])) {
            return [];
        }
        $allowed = $this->campaignRepository->getAllFormTableNames();
        $tables = array_filter(array_column($config['forms'], 'table'));
        $tables = array_values(array_intersect($tables, $allowed));

        return array_values(array_filter($tables, fn (string $t) => Schema::hasTable($t) && Schema::hasColumn($t, 'date') && Schema::hasColumn($t, 'agent')));
    }

    /** @return list<string> */
    private function resolveAllowedTablesWithCreatedAt(string $campaignCode): array
    {
        $tables = $this->resolveAllowedTables($campaignCode);

        return array_values(array_filter($tables, fn (string $t) => Schema::hasColumn($t, 'created_at')));
    }

    /**
     * @return list<string>
     */
    private function reportSystemDispositionCodes(): array
    {
        $codes = config('vicidial.report_system_disposition_codes', []);
        if (! is_array($codes) || $codes === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($code) => strtoupper(trim((string) $code)),
            $codes,
        )));
    }
}
