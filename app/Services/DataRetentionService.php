<?php

namespace App\Services;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Models\FormField;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
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

    public function __construct(private DataRetentionScheduleService $scheduleService) {}

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
    public function runDue(): array
    {
        $policies = DataRetentionPolicy::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->with('form')
            ->get();

        return $this->processPolicies($policies);
    }

    /**
     * Execute every active policy immediately for backwards-compatible callers.
     *
     * @return array{processed: int, deleted: int, skipped: int}
     */
    public function run(): array
    {
        return $this->processPolicies(
            DataRetentionPolicy::query()
                ->where('is_active', true)
                ->with('form')
                ->get(),
            true,
        );
    }

    /**
     * @return array{status: string, error: ?string, deleted: int}
     */
    public function runPolicy(DataRetentionPolicy $policy, bool $manual = false): array
    {
        $policy->loadMissing('form');
        $runAt = CarbonImmutable::now();

        try {
            $form = $policy->form;
            $tableName = (string) ($form?->table_name ?? '');

            if (! $form?->is_active || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
                return $this->recordOutcome(
                    $policy,
                    'skipped',
                    'The policy has no active form or a valid storage table.',
                    0,
                    $manual,
                    $runAt,
                );
            }

            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'date')) {
                return $this->recordOutcome(
                    $policy,
                    'skipped',
                    "Storage table '{$tableName}' is missing or has no date column.",
                    0,
                    $manual,
                    $runAt,
                );
            }

            $deletionMode = $policy->deletion_mode ?? 'whole_record';

            if ($deletionMode === 'selected_fields') {
                $updates = $this->selectedFieldUpdates($form, $tableName, $policy->selected_fields);

                if ($updates === null) {
                    return $this->recordOutcome(
                        $policy,
                        'skipped',
                        'The policy contains fields that are not eligible for safe clearing.',
                        0,
                        $manual,
                        $runAt,
                    );
                }

                $deleted = $this->retentionQuery($tableName, $policy)->update($updates);
            } elseif ($deletionMode === 'whole_record') {
                $deleted = $this->retentionQuery($tableName, $policy)->delete();
            } else {
                return $this->recordOutcome(
                    $policy,
                    'skipped',
                    'The policy has an unsupported deletion mode.',
                    0,
                    $manual,
                    $runAt,
                );
            }

            return $this->recordOutcome($policy, 'success', null, $deleted, $manual, $runAt);
        } catch (\Throwable $exception) {
            return $this->recordOutcome(
                $policy,
                'failed',
                $exception->getMessage() ?: $exception::class,
                0,
                $manual,
                $runAt,
            );
        }
    }

    /**
     * @param  Collection<int, DataRetentionPolicy>  $policies
     * @return array{processed: int, deleted: int, skipped: int}
     */
    private function processPolicies(Collection $policies, bool $manual = false): array
    {
        $summary = [
            'processed' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];

        foreach ($policies as $policy) {
            $result = $this->runPolicy($policy, $manual);

            if ($result['status'] === 'success') {
                $summary['processed']++;
                $summary['deleted'] += $result['deleted'];
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    /**
     * @return array{status: string, error: ?string, deleted: int}
     */
    private function recordOutcome(
        DataRetentionPolicy $policy,
        string $status,
        ?string $error,
        int $deleted,
        bool $manual,
        CarbonImmutable $runAt,
    ): array {
        $nextRunAt = $policy->next_run_at;

        if ($policy->run_mode === 'once') {
            $nextRunAt = null;

            if ($status === 'success') {
                $policy->is_active = false;
            }
        } elseif (! $manual) {
            try {
                $nextRunAt = $this->scheduleService->nextRunAt($policy, $runAt);
            } catch (\Throwable $exception) {
                $nextRunAt = $runAt->addDay();
                $status = 'failed';
                $error = $error ?? ($exception->getMessage() ?: $exception::class);
            }
        }

        $policy->forceFill([
            'last_run_at' => $runAt,
            'last_deleted_count' => $status === 'success' ? $deleted : 0,
            'last_run_status' => $status,
            'last_error' => $error,
            'next_run_at' => $nextRunAt,
        ])->save();

        if ($status === 'success') {
            Log::info('Data retention policy completed.', [
                'policy_id' => $policy->id,
                'form_id' => $policy->form_id,
                'deleted' => $deleted,
            ]);
        } else {
            Log::warning('Data retention policy did not complete.', [
                'policy_id' => $policy->id,
                'form_id' => $policy->form_id,
                'status' => $status,
                'reason' => $error,
            ]);
        }

        return [
            'status' => $status,
            'error' => $error,
            'deleted' => $status === 'success' ? $deleted : 0,
        ];
    }

    private function skipPolicy(DataRetentionPolicy $policy, string $reason): void
    {
        Log::warning('Data retention policy skipped.', [
            'policy_id' => $policy->id,
            'form_id' => $policy->form_id,
            'reason' => $reason,
        ]);
    }

    private function retentionQuery(string $tableName, DataRetentionPolicy $policy): Builder
    {
        $query = DB::table($tableName);

        if ($policy->from_date !== null) {
            $query->whereDate('date', '>=', $policy->from_date->format('Y-m-d'));
        }

        return $query->whereDate('date', '<=', $policy->to_date->format('Y-m-d'));
    }

    /**
     * @param  list<string>|null  $selectedFields
     * @return array<string, mixed>|null
     */
    private function selectedFieldUpdates(Form $form, string $tableName, ?array $selectedFields): ?array
    {
        if ($selectedFields === null || $selectedFields === [] || count($selectedFields) !== count(array_unique($selectedFields))) {
            return null;
        }

        $eligibleFields = $this->eligibleFields($form)->pluck('field_name')->all();
        if (array_diff($selectedFields, $eligibleFields) !== []) {
            return null;
        }

        $columns = collect(Schema::getColumns($tableName))->keyBy('name');
        $updates = [];

        foreach ($selectedFields as $fieldName) {
            if (! is_string($fieldName) || ! preg_match('/^[A-Za-z0-9_]+$/', $fieldName)) {
                return null;
            }

            $clearValue = $this->clearValueForColumn($columns->get($fieldName));
            if (! $clearValue['supported']) {
                return null;
            }

            $updates[$fieldName] = $clearValue['value'];
        }

        return $updates;
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
