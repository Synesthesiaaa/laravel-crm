<?php

namespace App\Services;

use App\Repositories\CampaignRepository;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    public function __construct(
        protected CampaignRepository $campaignRepository
    ) {}

    public function getActivityTrend(string $campaignCode, int $days = 14): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $config = $campaigns[$campaignCode] ?? null;
        if (!$config || empty($config['forms'])) {
            return ['labels' => [], 'values' => []];
        }
        $tables = array_filter(array_column($config['forms'], 'table'));
        $allowed = $this->campaignRepository->getAllFormTableNames();
        $tables = array_intersect($tables, $allowed);
        if (empty($tables)) {
            return ['labels' => [], 'values' => []];
        }

        $unions = [];
        foreach ($tables as $t) {
            $unions[] = "SELECT `date`, `agent` FROM `{$t}`";
        }
        $unionQuery = implode(' UNION ALL ', $unions);
        $sql = "SELECT `date`, COUNT(*) as total FROM ({$unionQuery}) as combined 
                WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                GROUP BY `date` ORDER BY `date` ASC";
        $rows = DB::select($sql, [$days]);
        $activityData = [];
        foreach ($rows as $row) {
            $activityData[$row->date] = (int) $row->total;
        }

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M d');
            $values[] = $activityData[$d] ?? 0;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public function getTopAgents(string $campaignCode, int $limit = 10): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $config = $campaigns[$campaignCode] ?? null;
        if (!$config || empty($config['forms'])) {
            return ['labels' => [], 'values' => []];
        }
        $tables = array_filter(array_column($config['forms'], 'table'));
        $allowed = $this->campaignRepository->getAllFormTableNames();
        $tables = array_intersect($tables, $allowed);
        if (empty($tables)) {
            return ['labels' => [], 'values' => []];
        }

        $unions = [];
        foreach ($tables as $t) {
            $unions[] = "SELECT `agent` FROM `{$t}`";
        }
        $unionQuery = implode(' UNION ALL ', $unions);
        $sql = "SELECT `agent`, COUNT(*) as total FROM ({$unionQuery}) as combined 
                WHERE `agent` IS NOT NULL AND `agent` != '' 
                GROUP BY `agent` ORDER BY total DESC LIMIT ?";
        $rows = DB::select($sql, [$limit]);
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $row->agent;
            $values[] = (int) $row->total;
        }
        return ['labels' => $labels, 'values' => $values];
    }
}
