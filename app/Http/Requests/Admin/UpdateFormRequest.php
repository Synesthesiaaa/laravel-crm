<?php

namespace App\Http\Requests\Admin;

use App\Models\Form;
use App\Models\FormField;
use App\Services\DashboardSalesRuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50', 'exists:campaigns,code'],
            'form_code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'table_name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'sales_rule' => ['sometimes', 'array:enabled,form_code,amount_field,trigger,conditions'],
            'sales_rule.enabled' => ['required_with:sales_rule', 'boolean'],
            'sales_rule.form_code' => ['required_with:sales_rule', 'string', 'max:50'],
            'sales_rule.amount_field' => ['nullable', 'string', 'max:100'],
            'sales_rule.trigger' => [
                'required_if:sales_rule.enabled,1',
                'nullable',
                'string',
                Rule::in(DashboardSalesRuleService::TRIGGERS),
            ],
            'sales_rule.conditions' => ['nullable', 'array', 'max:25'],
            'sales_rule.conditions.*' => ['array:field_name,accepted_values'],
            'sales_rule.conditions.*.field_name' => ['required', 'string', 'max:100'],
            'sales_rule.conditions.*.accepted_values' => ['required', 'array', 'min:1', 'max:20'],
            'sales_rule.conditions.*.accepted_values.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $form = $this->route('form');
            if ($form instanceof Form) {
                $identity = [
                    'campaign_code' => (string) $form->campaign_code,
                    'form_code' => (string) $form->form_code,
                    'table_name' => (string) $form->table_name,
                ];
                $changed = array_filter(
                    array_keys($identity),
                    fn (string $key): bool => $this->has($key)
                        && (string) $this->input($key) !== $identity[$key],
                );

                if ($changed !== []) {
                    try {
                        $hasMetadata = FormField::withTrashed()
                            ->where('campaign_code', $form->campaign_code)
                            ->where('form_type', $form->form_code)
                            ->exists();
                        $hasStoredRecords = preg_match('/^[A-Za-z0-9_]+$/', (string) $form->table_name) === 1
                            && Schema::hasTable($form->table_name)
                            && DB::table($form->table_name)->exists();

                        if ($hasMetadata || $hasStoredRecords) {
                            foreach ($changed as $field) {
                                $validator->errors()->add($field, 'Form identifiers cannot be changed after fields or records are in use.');
                            }
                        }
                    } catch (Throwable) {
                        foreach ($changed as $field) {
                            $validator->errors()->add($field, 'Form usage could not be verified. Try again or contact an administrator.');
                        }
                    }
                }
            }

            if (! $this->has('sales_rule') || ! $this->boolean('sales_rule.enabled')) {
                return;
            }

            if (! $form instanceof Form) {
                $validator->errors()->add('sales_rule.form_code', 'The selected form could not be loaded.');

                return;
            }

            $campaignCode = trim((string) $this->input('campaign_code', $form->campaign_code));
            $rule = is_array($this->input('sales_rule')) ? $this->input('sales_rule') : [];
            $rule['form_code'] = (string) $form->form_code;

            foreach (app(DashboardSalesRuleService::class)->validationErrors($campaignCode, [
                'mode' => DashboardSalesRuleService::MODE_CUSTOM,
                'forms' => [$rule],
            ]) as $error) {
                $key = str_replace('sales_forms.0', 'sales_rule', $error['key']);
                $validator->errors()->add($key, $error['message']);
            }
        });
    }

    public function messages(): array
    {
        return [
            'form_code.regex' => 'Form code may only contain lowercase letters, numbers, and underscores.',
            'name.required' => 'Form name is required.',
            'table_name.required' => 'Table name is required.',
            'table_name.regex' => 'Table name may only contain lowercase letters, numbers, and underscores.',
        ];
    }
}
