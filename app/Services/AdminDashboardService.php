<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    /** @return array<string, array{name: string, count: int, color: string}> */
    public function getFormStats(string $campaignCode): array
    {
        return Cache::remember("admin_form_stats_{$campaignCode}", 300, function () use ($campaignCode) {
            $campaignConfig = $this->campaignService->getCampaign($campaignCode) ?? ['name' => $campaignCode, 'forms' => []];
            $forms = $campaignConfig['forms'] ?? [];
            $tableNames = $this->campaignService->getAllFormTableNames();
            $stats = [];

            $tableList = [];
            foreach ($forms as $formCode => $formConfig) {
                $tableName = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
                if ($tableName !== '' && in_array($tableName, $tableNames, true)) {
                    $tableList[$formCode] = $tableName;
                } else {
                    $stats[$formCode] = [
                        'name' => $formConfig['name'] ?? $formCode,
                        'count' => 0,
                        'color' => $formConfig['color'] ?? 'blue',
                    ];
                }
            }

            if (! empty($tableList)) {
                $counts = $this->batchCountTables($tableList);
                foreach ($forms as $formCode => $formConfig) {
                    if (isset($counts[$formCode])) {
                        $stats[$formCode] = [
                            'name' => $formConfig['name'] ?? $formCode,
                            'count' => $counts[$formCode],
                            'color' => $formConfig['color'] ?? 'blue',
                        ];
                    }
                }
            }

            return $stats;
        });
    }

    public function getTotalUserCount(): int
    {
        return Cache::remember('admin_user_count', 300, fn () => User::count());
    }

    public function invalidateStats(string $campaignCode): void
    {
        Cache::forget("admin_form_stats_{$campaignCode}");
        Cache::forget('admin_user_count');
    }

    /** @param array<string, string> $tableList formCode => tableName */
    private function batchCountTables(array $tableList): array
    {
        $counts = [];
        foreach ($tableList as $formCode => $tableName) {
            try {
                $counts[$formCode] = DB::table($tableName)->count();
            } catch (\Throwable) {
                $counts[$formCode] = 0;
            }
        }

        return $counts;
    }
}
