<?php

namespace App\Services;

use App\Events\DashboardDataUpdated;
use App\Events\FormSubmitted;
use App\Repositories\FormFieldRepository;
use App\Repositories\FormSubmissionRepository;
use App\Support\OperationResult;
use App\Support\PercentageValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormSubmissionService
{
    private const WIDE_NUMBER_PRECISION = 65;

    private const WIDE_NUMBER_SCALE = 2;

    /**
     * Columns populated by the submission pipeline or framework internals.
     *
     * @var list<string>
     */
    private const SYSTEM_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'date',
        'request_id',
        'agent',
        'lead_id',
        'phone_number',
    ];

    public function __construct(
        protected CampaignService $campaignService,
        protected FormFieldRepository $formFieldRepository,
        protected FormSubmissionRepository $formSubmissionRepository,
        protected CallHistoryService $callHistoryService,
    ) {}

    public function submit(string $campaign, string $formType, array $data, string $agent, ?int $userId = null): OperationResult
    {
        $formConfig = $this->campaignService->getFormConfig($campaign, $formType);
        if (! $formConfig) {
            return OperationResult::failure('Invalid form.');
        }
        $tableName = $formConfig['table_name'];
        if (! $this->formFieldRepository->validateTableName($tableName, $this->campaignService->getAllFormTableNames())) {
            return OperationResult::failure('Invalid table.');
        }
        $fields = $this->formFieldRepository->getFieldsForForm($campaign, $formType);
        $fieldMap = $this->resolveStorageFieldMap($tableName, $fields);
        $this->setStorageFieldNames($fields, $fieldMap);
        $data = $this->mapSubmissionDataToStorageFields($data, $fieldMap);
        $this->ensureStorageTableAndColumns($tableName, $fields);

        $date = $this->sanitizeDate($data['date'] ?? '');
        if ($date === '') {
            return OperationResult::failure('Date is required.');
        }

        try {
            $recordId = DB::transaction(function () use ($tableName, $fields, $data, $agent, $userId, $campaign, $formType, $date): int {
                $merged = array_merge($data, [
                    'date' => $date,
                    'request_id' => $this->generateUniqueRequestId($tableName),
                ]);
                $prepared = $this->prepareFormRow($fields, $merged, $agent, $tableName);
                if ($prepared === null) {
                    throw new \RuntimeException('Invalid submission data.');
                }

                $id = $this->formSubmissionRepository->insert($tableName, $prepared);
                $historyResult = $this->callHistoryService->logFormSubmission(
                    $campaign,
                    $formType,
                    $id,
                    $agent,
                    isset($data['lead_id']) && $data['lead_id'] !== '' ? (int) $data['lead_id'] : null,
                    $data['phone_number'] ?? null,
                    'RECORDED',
                    null,
                    $userId,
                );
                if (! $historyResult->success) {
                    throw new \RuntimeException($historyResult->message ?? 'Unable to record form activity.');
                }

                return $id;
            });

            event(new FormSubmitted($campaign, $formType, $recordId, $agent));
            event(new DashboardDataUpdated($campaign, $formType, $recordId, 'submitted'));

            return OperationResult::success($recordId);
        } catch (\Throwable $e) {
            return OperationResult::failure($e->getMessage());
        }
    }

    /** @return array<string, mixed>|null */
    public function prepareFormRow(Collection $fields, array $data, string $agent, ?string $tableName = null): ?array
    {
        $date = $this->sanitizeDate($data['date'] ?? '');
        $requestId = trim((string) ($data['request_id'] ?? ''));
        if ($date === '' || $requestId === '') {
            return null;
        }
        $row = [
            'date' => $date,
            'request_id' => $requestId,
            'agent' => $agent,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach ($fields as $field) {
            $fieldName = $field->field_name;
            $colName = $field->storage_field_name ?? $fieldName;
            if (in_array($colName, self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            if ($field->field_type === 'multiselect') {
                $raw = $data[$colName] ?? $data[$fieldName] ?? [];
                if (! is_array($raw)) {
                    $raw = [];
                }
                $allowed = $field->optionValues();
                $picked = [];
                foreach ($raw as $item) {
                    $s = is_string($item) ? trim($item) : (string) $item;
                    if ($s === '') {
                        continue;
                    }
                    if ($allowed === [] || in_array($s, $allowed, true)) {
                        $picked[] = $s;
                    }
                }
                $picked = array_values(array_unique($picked));
                sort($picked);
                if ($field->is_required && $picked === []) {
                    throw new \InvalidArgumentException("Field '{$colName}' is required.");
                }
                if (! $field->is_required && $picked === []) {
                    $row[$colName] = null;
                } else {
                    $row[$colName] = json_encode($picked);
                }

                continue;
            }

            $value = $data[$colName] ?? $data[$fieldName] ?? '';
            $value = is_string($value) ? trim($value) : $value;
            if ($field->field_type === 'number') {
                $value = preg_replace('/[^0-9.]/', '', (string) $value);
            }
            if ($field->field_type === 'percentage') {
                $value = $this->storesPercentageAsNumeric($tableName, $colName)
                    ? PercentageValue::numeric($value)
                    : PercentageValue::normalize($value);
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
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function ($table) {
                $table->id();
                $table->date('date')->index();
                $table->string('request_id', 255)->index();
                $table->string('agent', 255)->index();
                $table->timestamps();
            });
        } else {
            // Base table safety: create missing required base columns.
            if (! Schema::hasColumn($tableName, 'date')) {
                Schema::table($tableName, function ($table) {
                    $table->date('date')->index();
                });
            }
            if (! Schema::hasColumn($tableName, 'request_id')) {
                Schema::table($tableName, function ($table) {
                    $table->string('request_id', 255)->index();
                });
            }
            if (! Schema::hasColumn($tableName, 'agent')) {
                Schema::table($tableName, function ($table) {
                    $table->string('agent', 255)->index();
                });
            }
            if (! Schema::hasColumn($tableName, 'created_at')) {
                Schema::table($tableName, function ($table) {
                    $table->timestamp('created_at')->nullable();
                });
            }
            if (! Schema::hasColumn($tableName, 'updated_at')) {
                Schema::table($tableName, function ($table) {
                    $table->timestamp('updated_at')->nullable();
                });
            }
        }

        $missingFieldCols = [];
        foreach ($fields as $field) {
            $colName = $field->storage_field_name ?? $field->field_name;
            if (in_array($colName, self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, $colName)) {
                $missingFieldCols[] = $field;
            }
        }

        if (! empty($missingFieldCols)) {
            Schema::table($tableName, function ($table) use ($missingFieldCols) {
                foreach ($missingFieldCols as $field) {
                    /** @var \App\Models\FormField $field */
                    $colName = $field->storage_field_name ?? $field->field_name;
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
                        case 'multiselect':
                            $table->text($colName)->nullable($nullable);

                            break;
                        case 'number':
                            if ($this->isIdentifierField($colName)) {
                                $table->string($colName, 255)->nullable($nullable);
                            } else {
                                $table->decimal($colName, self::WIDE_NUMBER_PRECISION, self::WIDE_NUMBER_SCALE)->nullable($nullable);
                            }

                            break;
                        case 'percentage':
                            $table->string($colName, 50)->nullable($nullable);

                            break;
                        case 'text':
                        default:
                            $table->string($colName, 255)->nullable($nullable);

                            break;
                    }
                }
            });
        }

        $this->alignNumericStorageColumns($tableName, $fields);
    }

    private function alignNumericStorageColumns(string $tableName, Collection $fields): void
    {
        $needsAlignment = $fields->contains(function ($field): bool {
            $colName = $field->storage_field_name ?? $field->field_name;

            return (string) $field->field_type === 'number' || $this->isIdentifierField($colName);
        });
        if (! $needsAlignment) {
            return;
        }

        $columns = collect(Schema::getColumns($tableName))->keyBy('name');
        $stringColumns = [];
        $wideNumberColumns = [];

        foreach ($fields as $field) {
            $colName = $field->storage_field_name ?? $field->field_name;
            if (in_array($colName, self::SYSTEM_COLUMNS, true)) {
                continue;
            }

            $column = $columns->get($colName);
            if (! is_array($column)) {
                continue;
            }

            $columnType = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
            if (! $this->isNumericColumnType($columnType)) {
                continue;
            }

            $nullable = (bool) ($column['nullable'] ?? true);
            if ($this->isIdentifierField($colName)) {
                $stringColumns[$colName] = $nullable;

                continue;
            }

            if ((string) $field->field_type !== 'number' || $this->isWideDecimalColumn($column)) {
                continue;
            }

            $wideNumberColumns[$colName] = $nullable;
        }

        if ($stringColumns === [] && $wideNumberColumns === []) {
            return;
        }

        Schema::table($tableName, function ($table) use ($stringColumns, $wideNumberColumns) {
            foreach ($stringColumns as $colName => $nullable) {
                $table->string($colName, 255)->nullable($nullable)->change();
            }

            foreach ($wideNumberColumns as $colName => $nullable) {
                $table->decimal($colName, self::WIDE_NUMBER_PRECISION, self::WIDE_NUMBER_SCALE)
                    ->nullable($nullable)
                    ->change();
            }
        });
    }

    private function isIdentifierField(string $columnName): bool
    {
        $columnName = strtolower($columnName);

        return preg_match('/(^|_)(phone|mobile|telephone)($|_)/', $columnName) === 1
            || preg_match('/(^|_)(id|code|number|no|num)$/', $columnName) === 1;
    }

    private function isNumericColumnType(string $columnType): bool
    {
        return preg_match('/^(bigint|decimal|double|float|integer|mediumint|numeric|real|smallint|tinyint)/', $columnType) === 1;
    }

    /** @param array<string, mixed> $column */
    private function isWideDecimalColumn(array $column): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite'
            && strtolower((string) ($column['type_name'] ?? '')) === 'numeric') {
            return true;
        }

        $definition = strtolower((string) ($column['type'] ?? ''));

        return preg_match(
            '/decimal\(\s*'.self::WIDE_NUMBER_PRECISION.'\s*,\s*'.self::WIDE_NUMBER_SCALE.'\s*\)/',
            $definition,
        ) === 1;
    }

    /**
     * Find a safe one-to-one mapping when a form field name and a storage column
     * drift apart. Ambiguous differences are left untouched for normal schema
     * validation and migration handling.
     *
     * @return array<string, string>
     */
    private function resolveStorageFieldMap(string $tableName, Collection $fields): array
    {
        if (! Schema::hasTable($tableName)) {
            return [];
        }

        $storageColumns = Schema::getColumnListing($tableName);
        $fieldNames = $fields
            ->pluck('field_name')
            ->filter(fn (mixed $fieldName): bool => is_string($fieldName) && $fieldName !== '')
            ->unique()
            ->values()
            ->all();
        $storageFieldNames = array_values(array_diff($storageColumns, self::SYSTEM_COLUMNS));
        $unmatchedFields = array_values(array_diff($fieldNames, $storageColumns));
        $unrepresentedColumns = array_values(array_diff($storageFieldNames, $fieldNames));

        if (count($unmatchedFields) !== 1 || count($unrepresentedColumns) !== 1) {
            return [];
        }

        return [$unmatchedFields[0] => $unrepresentedColumns[0]];
    }

    /**
     * @param  array<string, string>  $fieldMap
     */
    private function setStorageFieldNames(Collection $fields, array $fieldMap): void
    {
        foreach ($fields as $field) {
            $field->setAttribute(
                'storage_field_name',
                $fieldMap[$field->field_name] ?? $field->field_name,
            );
        }
    }

    /**
     * @param  array<string, string>  $fieldMap
     * @return array<string, mixed>
     */
    private function mapSubmissionDataToStorageFields(array $data, array $fieldMap): array
    {
        foreach ($fieldMap as $fieldName => $storageFieldName) {
            if (array_key_exists($fieldName, $data) && ! array_key_exists($storageFieldName, $data)) {
                $data[$storageFieldName] = $data[$fieldName];
            }

            unset($data[$fieldName]);
        }

        return $data;
    }

    protected function generateUniqueRequestId(string $tableName): string
    {
        for ($attempt = 0; $attempt < $this->requestIdGenerationAttempts(); $attempt++) {
            $requestId = now()->format('YmdHis').$this->requestIdRandomSuffix();
            if (! DB::table($tableName)->where('request_id', $requestId)->exists()) {
                return $requestId;
            }
        }

        throw new \RuntimeException('Unable to generate a unique request ID.');
    }

    protected function requestIdRandomSuffix(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function requestIdGenerationAttempts(): int
    {
        return 10;
    }

    private function sanitizeDate(string $input): string
    {
        $input = trim($input);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return $input;
        }

        return '';
    }

    private function storesPercentageAsNumeric(?string $tableName, string $columnName): bool
    {
        if ($tableName === null || ! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
            return false;
        }

        try {
            return in_array(Schema::getColumnType($tableName, $columnName), [
                'bigint',
                'decimal',
                'double',
                'float',
                'integer',
                'numeric',
                'smallint',
                'tinyint',
            ], true);
        } catch (\Throwable) {
            return false;
        }
    }
}
