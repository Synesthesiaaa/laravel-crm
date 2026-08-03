<?php

namespace App\Services;

use App\Models\DataRetentionPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DataRetentionService
{
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
}
