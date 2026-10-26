<?php

namespace App\Services;

use App\Events\DashboardDataUpdated;
use App\Events\FormSubmitted;
use App\Models\LeadImportRow;
use App\Repositories\FormFieldRepository;
use App\Repositories\FormSubmissionRepository;
use App\Support\FormFieldNumber;
use App\Support\FormFieldVisibility;
use App\Support\OperationResult;
use App\Support\PercentageValue;
use App\Support\SafeOperationFailure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FormSubmissionService
{
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
        protected FormStorageSchemaService $formStorageSchema,
    ) {}

    public function submit(string $campaign, string $formType, array $data, string $agent, ?int $userId = null): OperationResult
    {
        return $this->persistSubmission($campaign, $formType, $data, $agent, $userId, null, null, true);
    }

    public function submitImportRow(
        string $campaign,
        string $formType,
        array $data,
        string $agent,
        ?int $userId,
        int $leadImportId,
        int $rowNumber,
        string $requestId,
    ): OperationResult {
        return $this->persistSubmission(
            $campaign,
            $formType,
            $data,
            $agent,
            $userId,
            $requestId,
            ['lead_import_id' => $leadImportId, 'row_number' => $rowNumber],
            false,
        );
    }

    /**
     * @param  array{lead_import_id: int, row_number: int}|null  $importReceipt
     */
    private function persistSubmission(
        string $campaign,
        string $formType,
        array $data,
        string $agent,
        ?int $userId,
        ?string $stableRequestId,
        ?array $importReceipt,
        bool $catchPersistenceExceptions,
    ): OperationResult {
        $formConfig = $this->campaignService->getFormConfig($campaign, $formType);
        if (! $formConfig) {
            return OperationResult::failure('Invalid form.');
        }
        $tableName = $formConfig['table_name'];
        if (! $this->formFieldRepository->validateTableName($tableName, $this->campaignService->getAllFormTableNames())) {
            return OperationResult::failure('Invalid table.');
        }
        $fields = $this->formFieldRepository->getFieldsForForm($campaign, $formType);
        $visibilityValues = $data;
        foreach ($fields as $field) {
            if (! FormFieldVisibility::isVisible($field->visibility, $visibilityValues)) {
                unset($data[$field->field_name]);
            }
        }
        $fieldMap = $this->formStorageSchema->resolveStorageFieldMap($tableName, $fields);
        $this->setStorageFieldNames($fields, $fieldMap);
        $data = $this->mapSubmissionDataToStorageFields($data, $fieldMap);
        if (! $this->formStorageSchema->isReady($tableName, $fields)) {
            return OperationResult::failure('Form storage needs administrator provisioning before records can be saved.');
        }

        $date = $this->sanitizeDate($data['date'] ?? '');
        if ($date === '') {
            return OperationResult::failure('Date is required.');
        }

        try {
            $recordId = DB::transaction(function () use ($tableName, $fields, $data, $visibilityValues, $agent, $userId, $campaign, $formType, $date, $stableRequestId, $importReceipt): int {
                $merged = array_merge($data, [
                    'date' => $date,
                    'request_id' => $stableRequestId ?? $this->generateUniqueRequestId($tableName),
                ]);
                $prepared = $this->prepareFormRow($fields, $merged, $agent, $tableName, $visibilityValues);
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

                if ($importReceipt !== null) {
                    LeadImportRow::query()->create([
                        ...$importReceipt,
                        'request_id' => $stableRequestId ?? $merged['request_id'],
                        'status' => LeadImportRow::STATUS_IMPORTED,
                        'record_id' => $id,
                    ]);
                }

                return $id;
            });

            event(new FormSubmitted($campaign, $formType, $recordId, $agent));
            event(new DashboardDataUpdated($campaign, $formType, $recordId, 'submitted'));

            return OperationResult::success($recordId);
        } catch (\Throwable $e) {
            if (! $catchPersistenceExceptions) {
                throw $e;
            }

            return SafeOperationFailure::result($e, 'form_submission.save', 'The record could not be saved.');
        }
    }

    /** @return array<string, mixed>|null */
    public function prepareFormRow(
        Collection $fields,
        array $data,
        string $agent,
        ?string $tableName = null,
        ?array $visibilityValues = null,
    ): ?array {
        $visibilityValues ??= $data;
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
            if (! FormFieldVisibility::isVisible($field->visibility ?? null, $visibilityValues)) {
                continue;
            }

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
            if ($field->field_type === 'number'
                && ! FormFieldNumber::isIdentifierLike((string) $fieldName)
                && (string) $value !== ''
                && ! FormFieldNumber::isValidDecimal($value)) {
                throw new \InvalidArgumentException("Field '{$colName}' must be a valid decimal value.");
            }
            if ($field->field_type === 'percentage') {
                $value = $tableName !== null && $this->formStorageSchema->storesPercentageAsNumeric($tableName, $colName)
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
}
