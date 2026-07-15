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

            if ($markedSaleFields['configured']) {
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
                if ($a['submissions'] !== $b['submissions']) {
                    return $b['submissions'] <=> $a['submissions'];
                }
                if ($a['sales_count'] !== $b['sales_count']) {
                    return $b['sales_count'] <=> $a['sales_count'];
                }
                if ($a['sales_amount'] != $b['sales_amount']) {
                    return $b['sales_amount'] <=> $a['sales_amount'];
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
            if ($markedSaleFields['configured']) {
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

            if (! $markedSaleFields['configured'] && $hasDispositionRecords) {
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
     * @return array{sales: int, sales_amount: float, top_agent: string|null, top_agent_sales: int, top_agent_sales_amount: float, sales_by_form: list<array{form_code: string, form_name: string, sales: int, sales_amount: float}>}
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
        ];

        if ($until->lte($from)) {
            return $empty;
        }

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

        return [
            'sales' => $fieldDrivenSales['count'],
            'sales_amount' => round($fieldDrivenSales['amount'], 2),
            'top_agent' => $topAgent,
            'top_agent_sales' => $topAgent === null ? 0 : $fieldDrivenSales['counts'][$topAgent],
            'top_agent_sales_amount' => $topAgent === null ? 0.0 : round($fieldDrivenSales['amounts'][$topAgent], 2),
            'sales_by_form' => $fieldDrivenSales['sales_by_form'],
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
