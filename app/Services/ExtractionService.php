<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Support\PercentageValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExtractionService
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    /**
     * @param  array<string, mixed>  $campaignConfig
     * @return array<string, string> tableName => friendlyName
     */
    public function resolveTables(array $campaignConfig, string $dataType): array
    {
        $allowedTables = $this->campaignService->getAllFormTableNames();
        $tables = [];

        if ($dataType === 'all') {
            foreach ($campaignConfig['forms'] ?? [] as $formCode => $formConfig) {
                $table = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
                if ($table && in_array($table, $allowedTables, true)) {
                    $tables[$table] = $formConfig['name'] ?? $formCode;
                }
            }
        } elseif (isset($campaignConfig['forms'][$dataType])) {
            $fc = $campaignConfig['forms'][$dataType];
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
     * @param  resource  $handle
     * @param  array<string, string>  $tables
     */
    public function streamCsv($handle, array $tables, ?string $startDate, ?string $endDate, ?string $campaignCode = null): void
    {
        foreach ($tables as $tableName => $friendlyName) {
            // If the form table doesn't exist yet, skip it (prevents 500s).
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $columns = $this->columnsForTable($tableName, $campaignCode);
            if ($columns === []) {
                continue;
            }
            $query = DB::table($tableName);
            $query->select($columns);
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
            fputcsv($handle, $columns);
            $percentageColumns = $this->percentageColumnsForTable($tableName, $campaignCode);
            foreach ($query->cursor() as $row) {
                $formatted = $this->formatCsvRow((array) $row, $percentageColumns);
                fputcsv($handle, array_map(
                    static fn (string $column): mixed => $formatted[$column] ?? null,
                    $columns,
                ));
                fflush($handle);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function columnsForTable(string $tableName, ?string $campaignCode): array
    {
        $actualColumns = Schema::getColumnListing($tableName);
        $forms = $this->formsForTable($tableName, $campaignCode);
        $configuredColumns = [];

        if ($forms !== []) {
            $fields = FormField::query()
                ->where(function ($query) use ($forms) {
                    foreach ($forms as $form) {
                        $query->orWhere(function ($query) use ($form) {
                            $query->where('campaign_code', $form->campaign_code)
                                ->where('form_type', $form->form_code);
                        });
                    }
                })
                ->orderBy('field_order')
                ->orderBy('id')
                ->pluck('field_name');

            foreach ($fields as $fieldName) {
                if (in_array($fieldName, $actualColumns, true) && ! in_array($fieldName, $configuredColumns, true)) {
                    $configuredColumns[] = $fieldName;
                }
            }
        }

        $leadingColumns = array_values(array_filter(
            ['id', 'date', 'request_id', 'agent'],
            static fn (string $column): bool => in_array($column, $actualColumns, true),
        ));
        $trailingColumns = array_values(array_filter(
            ['created_at', 'updated_at'],
            static fn (string $column): bool => in_array($column, $actualColumns, true),
        ));
        $reservedColumns = array_merge($leadingColumns, $configuredColumns, $trailingColumns);
        $legacyColumns = array_values(array_filter(
            $actualColumns,
            static fn (string $column): bool => ! in_array($column, $reservedColumns, true),
        ));

        return array_values(array_unique(array_merge(
            $leadingColumns,
            $configuredColumns,
            $legacyColumns,
            $trailingColumns,
        )));
    }

    /**
     * @return list<\App\Models\Form>
     */
    private function formsForTable(string $tableName, ?string $campaignCode): array
    {
        $query = Form::query()->where('table_name', $tableName);
        if ($campaignCode !== null && $campaignCode !== '') {
            $query->where('campaign_code', $campaignCode);
        }

        return $query->get(['campaign_code', 'form_code'])->all();
    }

    /**
     * @return list<string>
     */
    private function percentageColumnsForTable(string $tableName, ?string $campaignCode): array
    {
        $forms = $this->formsForTable($tableName, $campaignCode);

        if ($forms === []) {
            return [];
        }

        return FormField::query()
            ->where('field_type', 'percentage')
            ->where(function ($query) use ($forms) {
                foreach ($forms as $form) {
                    $query->orWhere(function ($query) use ($form) {
                        $query->where('campaign_code', $form->campaign_code)
                            ->where('form_type', $form->form_code);
                    });
                }
            })
            ->pluck('field_name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $percentageColumns
     * @return array<string, mixed>
     */
    private function formatCsvRow(array $row, array $percentageColumns): array
    {
        foreach ($percentageColumns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = PercentageValue::display($row[$column]);
            }
        }

        return $row;
    }
}
