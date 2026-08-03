<?php

namespace App\Services;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DataRetentionService
{
    /**
     * Columns that are populated by the submission pipeline or identify the record itself.
     *
     * @var list<string>
     */
    private const SYSTEM_COLUMNS = [
        'id',
        'date',
        'created_at',
        'updated_at',
        'request_id',
        'agent',
        'lead_id',
        'phone_number',
    ];

    /**
     * @return Collection<int, \App\Models\FormField>
     */
    public function eligibleFields(Form $form): Collection
    {
        $tableName = (string) $form->table_name;

        if (! preg_match('/^[A-Za-z0-9_]+$/', $tableName) || ! Schema::hasTable($tableName)) {
            return collect();
        }

        $columns = collect(Schema::getColumns($tableName))->keyBy('name');

        return FormField::query()
            ->where('campaign_code', $form->campaign_code)
            ->where('form_type', $form->form_code)
            ->ordered()
            ->get()
            ->filter(function (FormField $field) use ($columns): bool {
                if (in_array($field->field_name, self::SYSTEM_COLUMNS, true)) {
                    return false;
                }

                $column = $columns->get($field->field_name);

                return $this->clearValueForColumn($column)['supported'];
            })
            ->values();
    }

    /**
     * @return array{processed: int, deleted: int, skipped: int}
     */
    public function run(): array
    {
        $summary = [
            'processed' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];

        $policies = DataRetentionPolicy::query()
            ->where('is_active', true)
            ->with('form')
            ->get();

        foreach ($policies as $policy) {
            try {
                $form = $policy->form;
                $tableName = (string) ($form?->table_name ?? '');

                if (! $form?->is_active || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
                    $this->skipPolicy($policy, 'The policy has no active form or a valid storage table.');
                    $summary['skipped']++;

                    continue;
                }

                if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'date')) {
                    $this->skipPolicy($policy, "Storage table '{$tableName}' is missing or has no date column.");
                    $summary['skipped']++;

                    continue;
                }

                $deleted = DB::table($tableName)
                    ->whereDate('date', '<=', $policy->cutoff_date->format('Y-m-d'))
                    ->delete();

                $policy->forceFill([
                    'last_run_at' => now(),
                    'last_deleted_count' => $deleted,
                ])->save();

                $summary['processed']++;
                $summary['deleted'] += $deleted;
            } catch (\Throwable $exception) {
                $summary['skipped']++;

                Log::error('Data retention policy failed.', [
                    'policy_id' => $policy->id,
                    'form_id' => $policy->form_id,
                    'exception' => $exception,
                ]);
            }
        }

        return $summary;
    }

    private function skipPolicy(DataRetentionPolicy $policy, string $reason): void
    {
        Log::warning('Data retention policy skipped.', [
            'policy_id' => $policy->id,
            'form_id' => $policy->form_id,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $column
     * @return array{supported: bool, value: mixed}
     */
    private function clearValueForColumn(?array $column): array
    {
        if ($column === null) {
            return ['supported' => false, 'value' => null];
        }

        if ((bool) ($column['nullable'] ?? false)) {
            return ['supported' => true, 'value' => null];
        }

        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));

        if (preg_match('/char|text|blob|string/', $type)) {
            return ['supported' => true, 'value' => ''];
        }

        if (preg_match('/bit|bool|decimal|double|float|int|numeric|real/', $type)) {
            return ['supported' => true, 'value' => 0];
        }

        return ['supported' => false, 'value' => null];
    }
}
