<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ExtractionService
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * @param  array<string, mixed> $campaignConfig
     * @param  string               $dataType
     * @return array<string, string> tableName => friendlyName
     */
    public function resolveTables(array $campaignConfig, string $dataType): array
    {
        $allowedTables = $this->campaignService->getAllFormTableNames();
        $tables        = [];

        if ($dataType === 'all') {
            foreach ($campaignConfig['forms'] ?? [] as $formCode => $formConfig) {
                $table = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
                if ($table && in_array($table, $allowedTables, true)) {
                    $tables[$table] = $formConfig['name'] ?? $formCode;
                }
            }
        } elseif (isset($campaignConfig['forms'][$dataType])) {
            $fc    = $campaignConfig['forms'][$dataType];
            $table = $fc['table_name'] ?? $fc['table'] ?? '';
            if ($table && in_array($table, $allowedTables, true)) {
                $tables[$table] = $fc['name'] ?? $dataType;
            }
        }

        return $tables;
    }

    /**
     * Streams a CSV of the given tables to the provided resource handle.
     *
     * @param  resource             $handle
     * @param  array<string, string> $tables
     */
    public function streamCsv($handle, array $tables, ?string $startDate, ?string $endDate): void
    {
        foreach ($tables as $tableName => $friendlyName) {
            $query = DB::table($tableName)->orderBy('id');
            if ($startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('date', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('date', '<=', $endDate);
            }
            $rows = $query->get();
            if ($rows->isEmpty()) {
                continue;
            }
            fputcsv($handle, array_keys((array) $rows->first()));
            foreach ($rows as $row) {
                fputcsv($handle, (array) $row);
            }
        }
    }
}
