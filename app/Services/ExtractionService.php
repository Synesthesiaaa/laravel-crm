<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $query = DB::table($tableName);
            if (Schema::hasColumn($tableName, 'id')) {
                $query->orderBy('id');
            }

            // Some form tables may not use a `date` column. Guard to prevent 500s.
            if (Schema::hasColumn($tableName, 'date')) {
                if ($startDate && $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('date', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('date', '<=', $endDate);
                }
            }

            // Stream rows to avoid loading the entire dataset into memory.
            // This prevents production 500 errors caused by timeouts/memory exhaustion.
            $headerWritten = false;
            foreach ($query->cursor() as $row) {
                if (! $headerWritten) {
                    fputcsv($handle, array_keys((array) $row));
                    $headerWritten = true;
                }
                fputcsv($handle, (array) $row);
                fflush($handle);
            }
        }
    }
}
