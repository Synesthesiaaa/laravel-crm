<?php

namespace App\Services;

use App\Models\FormField;
use App\Repositories\CampaignRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardSalesRuleService
{
    public const MODE_CUSTOM = 'custom';

    public const MODE_LEGACY = 'legacy';

    /** @var list<string> */
    private const TAG_FIELD_TYPES = ['text', 'select'];

    public function __construct(
        protected CampaignRepository $campaignRepository,
    ) {}

    /**
     * Resolve a stored sales configuration into safe, queryable metadata.
     *
     * @param  array<string, mixed>|null  $salesConfig
     * @return array{mode: string, forms: list<array{form_code: string, form_name: string, table: string, amount_field: string|null, conditions: list<array{field_name: string, accepted_values: list<string>}>}>, warnings: list<string>}
     */
    public function resolveForCampaign(string $campaignCode, ?array $salesConfig): array
    {
        $mode = ($salesConfig['mode'] ?? null) === self::MODE_CUSTOM
            ? self::MODE_CUSTOM
            : self::MODE_LEGACY;

        if ($mode === self::MODE_LEGACY) {
            return [
                'mode' => self::MODE_LEGACY,
                'forms' => [],
                'warnings' => [],
            ];
        }

        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $campaignForms = $campaigns[$campaignCode]['forms'] ?? [];
        $allowedTables = $this->campaignRepository->getAllFormTableNames();
        $formGroups = is_array($salesConfig['forms'] ?? null) ? $salesConfig['forms'] : [];
        $warnings = [];
        $forms = [];

        foreach ($formGroups as $formGroup) {
            if (! is_array($formGroup)) {
                $warnings[] = 'A sales form rule is not a valid object.';

                continue;
            }

            $formCode = trim((string) ($formGroup['form_code'] ?? ''));
            $formConfig = $campaignForms[$formCode] ?? null;
            if ($formCode === '' || ! is_array($formConfig)) {
                $warnings[] = $formCode === ''
                    ? 'A sales form rule is missing its form.'
                    : "Sales form rule '{$formCode}' is not active for this campaign.";

                continue;
            }

            $tableName = (string) ($formConfig['table_name'] ?? $formConfig['table'] ?? '');
            if ($tableName === '' || ! in_array($tableName, $allowedTables, true) || ! Schema::hasTable($tableName)) {
                $warnings[] = "Sales form '{$formCode}' does not have a safe registered table.";

                continue;
            }

            $fields = FormField::query()
                ->where('campaign_code', $campaignCode)
                ->where('form_type', $formCode)
                ->get(['field_name', 'field_label', 'field_type', 'is_sale_amount']);
            $fieldsByName = $fields->keyBy(static fn (FormField $field): string => (string) $field->field_name);

            $amountField = $this->resolveAmountField(
                $formGroup['amount_field'] ?? null,
                $fieldsByName,
                $tableName,
                $formCode,
                $warnings,
            );
            $conditions = $this->resolveConditions(
                $formGroup['conditions'] ?? null,
                $fieldsByName,
                $tableName,
                $formCode,
                $warnings,
            );

            $rawConditions = $formGroup['conditions'] ?? null;
            $markerOnlyRule = $conditions === []
                && is_array($rawConditions)
                && $rawConditions === []
                && $this->isMarkedSaleAmountField($amountField, $fieldsByName);

            if ($conditions === [] && ! $markerOnlyRule) {
                $warnings[] = is_array($rawConditions) && $rawConditions === []
                    ? "Sales form '{$formCode}' needs a text/select tag condition or a numeric field marked as a sale amount."
                    : "Sales form '{$formCode}' has no valid tag conditions.";

                continue;
            }

            $forms[] = [
                'form_code' => $formCode,
                'form_name' => (string) ($formConfig['name'] ?? $formCode),
                'table' => $tableName,
                'amount_field' => $amountField,
                'conditions' => $conditions,
            ];
        }

        return [
            'mode' => self::MODE_CUSTOM,
            'forms' => $forms,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Return the active campaign fields that are safe to expose in the rule editor.
     *
     * @return list<array{code: string, name: string, fields: list<array{name: string, label: string, type: string, options: list<string>, is_amount: bool, is_sale_amount: bool, is_tag: bool}>}>
     */
    public function editorData(string $campaignCode): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $campaignForms = $campaigns[$campaignCode]['forms'] ?? [];
        $allowedTables = $this->campaignRepository->getAllFormTableNames();
        $editorForms = [];

        foreach ($campaignForms as $formCode => $formConfig) {
            $tableName = (string) ($formConfig['table_name'] ?? $formConfig['table'] ?? '');
            if ($tableName === '' || ! in_array($tableName, $allowedTables, true) || ! Schema::hasTable($tableName)) {
                continue;
            }

            $fields = FormField::query()
                ->where('campaign_code', $campaignCode)
                ->where('form_type', (string) $formCode)
                ->orderBy('field_order')
                ->orderBy('id')
                ->get(['field_name', 'field_label', 'field_type', 'options', 'is_sale_amount'])
                ->filter(fn (FormField $field): bool => Schema::hasColumn($tableName, (string) $field->field_name))
                ->map(fn (FormField $field): array => [
                    'name' => (string) $field->field_name,
                    'label' => (string) ($field->field_label ?: $field->field_name),
                    'type' => (string) $field->field_type,
                    'options' => $field->optionValues(),
                    'is_amount' => $field->field_type === 'number',
                    'is_sale_amount' => (bool) $field->is_sale_amount,
                    'is_tag' => in_array((string) $field->field_type, self::TAG_FIELD_TYPES, true),
                ])
                ->values()
                ->all();

            $editorForms[] = [
                'code' => (string) $formCode,
                'name' => (string) ($formConfig['name'] ?? $formCode),
                'fields' => $fields,
            ];
        }

        return $editorForms;
    }

    /**
     * Return field-level validation errors for a custom configuration.
     *
     * @param  array<string, mixed>  $salesConfig
     * @return list<array{key: string, message: string}>
     */
    public function validationErrors(string $campaignCode, array $salesConfig): array
    {
        if (($salesConfig['mode'] ?? null) !== self::MODE_CUSTOM) {
            return [];
        }

        $metadata = $this->formMetadata($campaignCode);
        $errors = [];
        $formGroups = $salesConfig['forms'] ?? null;
        if (! is_array($formGroups) || $formGroups === []) {
            return [['key' => 'sales_forms', 'message' => 'Add at least one complete sales rule.']];
        }

        foreach ($formGroups as $index => $formGroup) {
            $prefix = 'sales_forms.'.$index;
            if (! is_array($formGroup)) {
                $errors[] = ['key' => $prefix, 'message' => 'This sales rule is invalid.'];

                continue;
            }

            $formCode = trim((string) ($formGroup['form_code'] ?? ''));
            $form = $metadata[$formCode] ?? null;
            if ($formCode === '' || $form === null) {
                $errors[] = ['key' => $prefix.'.form_code', 'message' => 'Select an active campaign form.'];

                continue;
            }

            $amountField = trim((string) ($formGroup['amount_field'] ?? ''));
            if ($amountField !== '' && (($form['fields'][$amountField]['type'] ?? null) !== 'number')) {
                $errors[] = ['key' => $prefix.'.amount_field', 'message' => 'Choose a numeric amount field from this form.'];
            }

            $conditions = $formGroup['conditions'] ?? null;
            if (! is_array($conditions)) {
                $errors[] = ['key' => $prefix.'.conditions', 'message' => 'Add a tag condition or select a marked sale amount field.'];

                continue;
            }

            if ($conditions === []) {
                if ($amountField === '' || ! ($form['fields'][$amountField]['is_sale_amount'] ?? false)) {
                    $errors[] = ['key' => $prefix.'.conditions', 'message' => 'Select a marked sale amount field when no tag condition is configured.'];
                }

                continue;
            }

            $validCondition = false;
            foreach ($conditions as $conditionIndex => $condition) {
                $conditionPrefix = $prefix.'.conditions.'.$conditionIndex;
                if (! is_array($condition)) {
                    $errors[] = ['key' => $conditionPrefix, 'message' => 'This tag condition is invalid.'];

                    continue;
                }

                $fieldName = trim((string) ($condition['field_name'] ?? ''));
                $field = $form['fields'][$fieldName] ?? null;
                if ($fieldName === '' || $field === null || ! $field['is_tag']) {
                    $errors[] = ['key' => $conditionPrefix.'.field_name', 'message' => 'Choose a text or select tag field.'];

                    continue;
                }

                $values = $this->normalizeValues($condition['accepted_values'] ?? null);
                if ($values === []) {
                    $errors[] = ['key' => $conditionPrefix.'.accepted_values', 'message' => 'Enter at least one accepted value.'];

                    continue;
                }

                $validCondition = true;
            }

            if (! $validCondition) {
                $errors[] = ['key' => $prefix.'.conditions', 'message' => 'Add at least one complete tag condition.'];
            }
        }

        return $errors;
    }

    /**
     * Normalize a validated custom configuration before storing it in the layout JSON.
     *
     * @param  array<string, mixed>  $salesConfig
     * @return array{mode: string, forms: list<array{form_code: string, amount_field: string|null, conditions: list<array{field_name: string, accepted_values: list<string>}>}>}
     */
    public function normalizeForPersistence(array $salesConfig): array
    {
        $forms = [];
        foreach ((array) ($salesConfig['forms'] ?? []) as $formGroup) {
            if (! is_array($formGroup)) {
                continue;
            }

            $conditions = [];
            foreach ((array) ($formGroup['conditions'] ?? []) as $condition) {
                if (! is_array($condition)) {
                    continue;
                }

                $values = $this->normalizeValues($condition['accepted_values'] ?? null);
                if ($values === []) {
                    continue;
                }

                $conditions[] = [
                    'field_name' => trim((string) ($condition['field_name'] ?? '')),
                    'accepted_values' => $values,
                ];
            }

            $forms[] = [
                'form_code' => trim((string) ($formGroup['form_code'] ?? '')),
                'amount_field' => ($amount = trim((string) ($formGroup['amount_field'] ?? ''))) !== '' ? $amount : null,
                'conditions' => $conditions,
            ];
        }

        return [
            'mode' => self::MODE_CUSTOM,
            'forms' => $forms,
        ];
    }

    /**
     * @return array<string, array{table: string, fields: array<string, array{type: string, is_tag: bool}>}>
     */
    private function formMetadata(string $campaignCode): array
    {
        $campaigns = $this->campaignRepository->getCampaignsWithForms();
        $campaignForms = $campaigns[$campaignCode]['forms'] ?? [];
        $allowedTables = $this->campaignRepository->getAllFormTableNames();
        $metadata = [];

        foreach ($campaignForms as $formCode => $formConfig) {
            $tableName = (string) ($formConfig['table_name'] ?? $formConfig['table'] ?? '');
            if ($tableName === '' || ! in_array($tableName, $allowedTables, true) || ! Schema::hasTable($tableName)) {
                continue;
            }

            $fields = FormField::query()
                ->where('campaign_code', $campaignCode)
                ->where('form_type', (string) $formCode)
                ->get(['field_name', 'field_type', 'is_sale_amount'])
                ->filter(fn (FormField $field): bool => Schema::hasColumn($tableName, (string) $field->field_name))
                ->mapWithKeys(fn (FormField $field): array => [(string) $field->field_name => [
                    'type' => (string) $field->field_type,
                    'is_sale_amount' => (bool) $field->is_sale_amount,
                    'is_tag' => in_array((string) $field->field_type, self::TAG_FIELD_TYPES, true),
                ]])
                ->all();

            $metadata[(string) $formCode] = [
                'table' => $tableName,
                'fields' => $fields,
            ];
        }

        return $metadata;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, FormField>  $fieldsByName
     * @param  list<string>  $warnings
     */
    private function resolveAmountField(
        mixed $amountField,
        Collection $fieldsByName,
        string $tableName,
        string $formCode,
        array &$warnings,
    ): ?string {
        $fieldName = trim((string) $amountField);
        if ($fieldName === '') {
            return null;
        }

        $field = $fieldsByName->get($fieldName);
        if (! $field || $field->field_type !== 'number' || ! Schema::hasColumn($tableName, $fieldName)) {
            $warnings[] = "Sales form '{$formCode}' has an invalid amount field '{$fieldName}'.";

            return null;
        }

        return $fieldName;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, FormField>  $fieldsByName
     * @param  list<string>  $warnings
     * @return list<array{field_name: string, accepted_values: list<string>}>
     */
    private function resolveConditions(
        mixed $rawConditions,
        Collection $fieldsByName,
        string $tableName,
        string $formCode,
        array &$warnings,
    ): array {
        if (! is_array($rawConditions)) {
            return [];
        }

        $conditions = [];
        foreach ($rawConditions as $rawCondition) {
            if (! is_array($rawCondition)) {
                continue;
            }

            $fieldName = trim((string) ($rawCondition['field_name'] ?? ''));
            $field = $fieldsByName->get($fieldName);
            if ($fieldName === ''
                || ! $field
                || ! in_array((string) $field->field_type, self::TAG_FIELD_TYPES, true)
                || ! Schema::hasColumn($tableName, $fieldName)) {
                if ($fieldName !== '') {
                    $warnings[] = "Sales form '{$formCode}' has an invalid tag field '{$fieldName}'.";
                }

                continue;
            }

            $values = $this->normalizeValues($rawCondition['accepted_values'] ?? null);
            if ($values === []) {
                $warnings[] = "Sales form '{$formCode}' tag field '{$fieldName}' has no accepted values.";

                continue;
            }

            $conditions[] = [
                'field_name' => $fieldName,
                'accepted_values' => $values,
            ];
        }

        return $conditions;
    }

    /**
     * @return list<string>
     */
    private function normalizeValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $value = $this->normalizeValue((string) $value);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeValue(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    /**
     * Determine whether a selected amount field may act as a marker-only rule.
     *
     * @param  Collection<string, FormField>  $fieldsByName
     */
    private function isMarkedSaleAmountField(?string $fieldName, Collection $fieldsByName): bool
    {
        if ($fieldName === null || $fieldName === '') {
            return false;
        }

        $field = $fieldsByName->get($fieldName);

        return $field instanceof FormField
            && $field->field_type === 'number'
            && (bool) $field->is_sale_amount;
    }
}
