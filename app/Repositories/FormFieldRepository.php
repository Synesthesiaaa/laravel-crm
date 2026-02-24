<?php

namespace App\Repositories;

use App\Contracts\Repositories\FormFieldRepositoryInterface;
use App\Models\FormField;
use Illuminate\Support\Collection;

class FormFieldRepository implements FormFieldRepositoryInterface
{
    public function getFieldsForForm(string $campaignCode, string $formType): Collection
    {
        return FormField::where('campaign_code', $campaignCode)
            ->where('form_type', $formType)
            ->orderBy('field_order')
            ->orderBy('id')
            ->get();
    }

    public function getCategorizedFields(string $campaignCode, string $formType): array
    {
        $fields = $this->getFieldsForForm($campaignCode, $formType);
        $vici = [];
        $campaign = [];
        foreach ($fields as $field) {
            $arr = [
                'name' => $field->field_name,
                'label' => $field->field_label,
                'type' => $field->field_type,
                'required' => $field->is_required,
                'options' => $field->options ? (is_string($field->options) ? json_decode($field->options, true) : $field->options) : null,
                'vici_params' => $field->vici_params,
                'field_width' => $field->field_width ?? 'full',
            ];
            if (!empty($field->vici_params)) {
                $vici[] = $arr;
            } else {
                $campaign[] = $arr;
            }
        }
        return [
            'vici' => $vici,
            'campaign' => $campaign,
            'all' => $fields->all(),
        ];
    }

    public function validateTableName(string $tableName, ?array $allowedTables = null): bool
    {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        if ($tableName === '') {
            return false;
        }
        if ($allowedTables !== null && !in_array($tableName, $allowedTables, true)) {
            return false;
        }
        $sqlKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'EXEC', 'EXECUTE'];
        return !in_array(strtoupper($tableName), $sqlKeywords, true);
    }
}
