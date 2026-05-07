<?php

namespace App\Services;

use App\Repositories\CampaignRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardStatsService
{
    public function __construct(
        protected CampaignRepository $campaignRepository,
    ) {}

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

        $ym = sprintf('%04d_%02d', now()->year, now()->month);
        Cache::forget("activity_trend_monthly_{$campaignCode}_{$ym}");

        $hours = (int) config('dashboard.kpi_window_hours', 9);
        Cache::forget("dashboard_kpis_{$campaignCode}_{$hours}");
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
}
