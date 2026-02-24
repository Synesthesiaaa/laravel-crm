<?php

namespace App\Services;

use App\Repositories\CampaignRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    public function __construct(
        protected CampaignRepository $campaignRepository
    ) {}

    public function getActivityTrend(string $campaignCode, int $days = 14): array
    {
        return Cache::remember("activity_trend_{$campaignCode}_{$days}", 300, function () use ($campaignCode, $days) {
            $tables = $this->resolveAllowedTables($campaignCode);
            if (empty($tables)) {
                return ['labels' => [], 'values' => []];
            }

            $cutoff = now()->subDays($days)->format('Y-m-d');
            $queries = [];
            foreach ($tables as $t) {
                $queries[] = DB::table($t)
                    ->select(DB::raw("'$t' as source, `date`"))
                    ->where('date', '>=', $cutoff);
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

            $labels = [];
            $values = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d        = now()->subDays($i)->format('Y-m-d');
                $labels[] = now()->subDays($i)->format('M d');
                $values[] = $activityData[$d] ?? 0;
            }
            return ['labels' => $labels, 'values' => $values];
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
    }

    /** @return list<string> */
    private function resolveAllowedTables(string $campaignCode): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $config    = $campaigns[$campaignCode] ?? null;
        if (!$config || empty($config['forms'])) {
            return [];
        }
        $allowed = $this->campaignRepository->getAllFormTableNames();
        $tables  = array_filter(array_column($config['forms'], 'table'));
        return array_values(array_intersect($tables, $allowed));
    }
}
