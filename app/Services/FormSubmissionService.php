<?php

namespace App\Services;

use App\Events\FormSubmitted;
use App\Repositories\FormFieldRepository;
use App\Repositories\FormSubmissionRepository;
use App\Support\OperationResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormSubmissionService
{
    public function __construct(
        protected CampaignService $campaignService,
        protected FormFieldRepository $formFieldRepository,
        protected FormSubmissionRepository $formSubmissionRepository,
        protected CallHistoryService $callHistoryService
    ) {}

    public function submit(string $campaign, string $formType, array $data, string $agent): OperationResult
    {
        $formConfig = $this->campaignService->getFormConfig($campaign, $formType);
        if (!$formConfig) {
            return OperationResult::failure('Invalid form.');
        }
        $tableName = $formConfig['table_name'];
        if (!$this->formFieldRepository->validateTableName($tableName, $this->campaignService->getAllFormTableNames())) {
            return OperationResult::failure('Invalid table.');
        }
        $fields  = $this->formFieldRepository->getFieldsForForm($campaign, $formType);
        $this->ensureStorageTableAndColumns($tableName, $fields);
        $prepared = $this->prepareFormRow($fields, $data, $agent);
        if ($prepared === null) {
            return OperationResult::failure('Date and Request ID are required.');
        }

        try {
            $recordId = DB::transaction(function () use ($tableName, $prepared, $campaign, $formType, $agent, $data): int {
                $id = $this->formSubmissionRepository->insert($tableName, $prepared);
                $this->callHistoryService->logFormSubmission(
                    $campaign,
                    $formType,
                    $id,
                    $agent,
                    isset($data['lead_id']) && $data['lead_id'] !== '' ? (int) $data['lead_id'] : null,
                    $data['phone_number'] ?? null
                );
                return $id;
            });

            event(new FormSubmitted($campaign, $formType, $recordId, $agent));

            return OperationResult::success($recordId);
        } catch (\Throwable $e) {
            return OperationResult::failure($e->getMessage());
        }
    }

    /** @return array<string, mixed>|null */
    public function prepareFormRow(Collection $fields, array $data, string $agent): ?array
    {
        $date      = $this->sanitizeDate($data['date'] ?? '');
        $requestId = trim((string) ($data['request_id'] ?? ''));
        if ($date === '' || $requestId === '') {
            return null;
        }
        $row = [
            'date'       => $date,
            'request_id' => $requestId,
            'agent'      => $agent,
        ];
        foreach ($fields as $field) {
            $colName = $field->field_name;
            if (in_array($colName, ['date', 'request_id', 'agent', 'id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $value = $data[$colName] ?? '';
            $value = is_string($value) ? trim($value) : $value;
            if ($field->field_type === 'number') {
                $value = preg_replace('/[^0-9.]/', '', (string) $value);
            }
            if ($field->is_required && (string) $value === '') {
                throw new \InvalidArgumentException("Field '{$colName}' is required.");
            }
            // If it's optional and empty, store NULL (better than empty string for numeric/date columns).
            if (! $field->is_required && (string) $value === '') {
                $value = null;
            }
            $row[$colName] = $value;
        }
        return $row;
    }

    /**
     * Ensure the per-form storage table exists and contains columns for all active form fields.
     * This allows creating new forms (e.g. table_name = 'ploan') without manually running migrations.
     */
    protected function ensureStorageTableAndColumns(string $tableName, Collection $fields): void
    {
        $systemColumns = ['date', 'request_id', 'agent', 'id', 'created_at', 'updated_at'];

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function ($table) {
                $table->id();
                $table->date('date')->index();
                $table->string('request_id', 255)->index();
                $table->string('agent', 255)->index();
                $table->timestamps();
            });
        } else {
            // Base table safety: create missing required base columns.
            if (!Schema::hasColumn($tableName, 'date')) {
                Schema::table($tableName, function ($table) {
                    $table->date('date')->index();
                });
            }
            if (!Schema::hasColumn($tableName, 'request_id')) {
                Schema::table($tableName, function ($table) {
                    $table->string('request_id', 255)->index();
                });
            }
            if (!Schema::hasColumn($tableName, 'agent')) {
                Schema::table($tableName, function ($table) {
                    $table->string('agent', 255)->index();
                });
            }
        }

        $missingFieldCols = [];
        foreach ($fields as $field) {
            $colName = $field->field_name;
            if (in_array($colName, $systemColumns, true)) {
                continue;
            }
            if (!Schema::hasColumn($tableName, $colName)) {
                $missingFieldCols[] = $field;
            }
        }

        if (empty($missingFieldCols)) {
            return;
        }

        Schema::table($tableName, function ($table) use ($missingFieldCols) {
            foreach ($missingFieldCols as $field) {
                /** @var \App\Models\FormField $field */
                $colName = $field->field_name;
                $nullable = ! $field->is_required;
                $type = (string) $field->field_type;

                switch ($type) {
                    case 'textarea':
                        $table->text($colName)->nullable($nullable);
                        break;
                    case 'date':
                        $table->date($colName)->nullable($nullable);
                        break;
                    case 'select':
                        $table->string($colName, 255)->nullable($nullable);
                        break;
                    case 'number':
                        // Most of your known numeric fields (amount/rate) use 2 decimals.
                        $table->decimal($colName, 10, 2)->nullable($nullable);
                        break;
                    case 'text':
                    default:
                        $table->string($colName, 255)->nullable($nullable);
                        break;
                }
            }
        });
    }

    public function generateRequestId(string $tableName): string
    {
        $allowed = $this->campaignService->getAllFormTableNames();
        if (!in_array($tableName, $allowed, true)) {
            $tableName = 'ezycash';
        }
        $datePrefix = now()->format('ymd');
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'request_id')) {
            return $datePrefix . '001';
        }
        $count      = DB::table($tableName)->where('request_id', 'like', $datePrefix . '%')->count();
        $nextId     = $count + 1;
        return $datePrefix . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
    }

    private function sanitizeDate(string $input): string
    {
        $input = trim($input);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return $input;
        }
        return '';
    }
}
