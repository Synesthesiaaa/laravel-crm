<?php

namespace App\Services;

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

            if (Schema::hasTable('campaign_disposition_records') && $saleCodes !== []) {
                $rows = DB::table('campaign_disposition_records')
                    ->where('campaign_code', $campaignCode)
                    ->whereIn('disposition_code', $saleCodes)
                    ->whereNotNull('called_at')
                    ->whereDate('called_at', '>=', $monthStart)
                    ->whereDate('called_at', '<=', $today)
                    ->whereNotNull('agent')
                    ->where('agent', '!=', '')
                    ->select(['agent', 'lead_data_json'])
                    ->get();

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
     * @return array{calls: int, sales: int, top_agent: string|null, top_agent_calls: int}
     */
    public function getKpisForCampaign(string $campaignCode): array
    {
        $hours = (int) config('dashboard.kpi_window_hours', 9);

        return Cache::remember("dashboard_kpis_{$campaignCode}_{$hours}", 60, function () use ($campaignCode, $hours) {
            $empty = [
                'calls' => 0,
                'sales' => 0,
                'top_agent' => null,
                'top_agent_calls' => 0,
            ];

            if (! Schema::hasTable('campaign_disposition_records')) {
                return $empty;
            }

            $since = now()->subHours($hours);

            $calls = (int) DB::table('campaign_disposition_records')
                ->where('campaign_code', $campaignCode)
                ->whereNotNull('called_at')
                ->where('called_at', '>=', $since)
                ->count();

            /** @var list<string> $saleCodes */
            $saleCodes = config('dashboard.sale_disposition_codes', ['SALE']);
            $saleCodes = array_values(array_filter($saleCodes, static fn ($c) => is_string($c) && $c !== ''));

            $sales = 0;
            if ($saleCodes !== []) {
                $sales = (int) DB::table('campaign_disposition_records')
                    ->where('campaign_code', $campaignCode)
                    ->whereNotNull('called_at')
                    ->where('called_at', '>=', $since)
                    ->whereIn('disposition_code', $saleCodes)
                    ->count();
            }

            $top = DB::table('campaign_disposition_records')
                ->where('campaign_code', $campaignCode)
                ->whereNotNull('called_at')
                ->where('called_at', '>=', $since)
                ->whereNotNull('agent')
                ->where('agent', '!=', '')
                ->select('agent', DB::raw('COUNT(*) as total'))
                ->groupBy('agent')
                ->orderByDesc('total')
                ->orderBy('agent')
                ->first();

            return [
                'calls' => $calls,
                'sales' => $sales,
                'top_agent' => $top->agent ?? null,
                'top_agent_calls' => isset($top->total) ? (int) $top->total : 0,
            ];
        });
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

        $hours = (int) config('dashboard.kpi_window_hours', 9);
        Cache::forget("dashboard_kpis_{$campaignCode}_{$hours}");

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
}
