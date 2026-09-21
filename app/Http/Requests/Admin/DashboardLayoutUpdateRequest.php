<?php

namespace App\Http\Requests\Admin;

use App\Models\Campaign;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DashboardLayoutUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $sectionKeys = array_keys(DashboardLayoutService::sectionDefinitions());

        return [
            'amounts' => ['sometimes', 'array:enabled,total,change,charts,tables'],
            'amounts.enabled' => ['sometimes', 'boolean'],
            'amounts.total' => ['sometimes', 'boolean'],
            'amounts.change' => ['sometimes', 'boolean'],
            'amounts.charts' => ['sometimes', 'boolean'],
            'amounts.tables' => ['sometimes', 'boolean'],
            'section_order' => ['required', 'array', 'size:'.count($sectionKeys)],
            'section_order.*' => ['required', 'string', 'distinct', Rule::in($sectionKeys)],
            'visible_sections' => ['nullable', 'array', 'min:1'],
            'visible_sections.*' => ['required', 'string', 'distinct', Rule::in($sectionKeys)],
            'campaign_code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::exists(Campaign::class, 'code')->where(static fn ($query) => $query->where('is_active', true)),
            ],
            'sales_mode' => ['sometimes', 'string', Rule::in([
                DashboardSalesRuleService::MODE_LEGACY,
                DashboardSalesRuleService::MODE_CUSTOM,
            ])],
            'sales_forms' => ['sometimes', 'array', 'max:25'],
            'sales_forms.*' => ['array:form_code,amount_field,trigger,conditions'],
            'sales_forms.*.form_code' => ['required', 'string', 'max:50'],
            'sales_forms.*.amount_field' => ['nullable', 'string', 'max:100'],
            'sales_forms.*.trigger' => ['sometimes', 'string', Rule::in(DashboardSalesRuleService::TRIGGERS)],
            'sales_forms.*.conditions' => ['sometimes', 'array', 'max:25'],
            'sales_forms.*.conditions.*' => ['array:field_name,accepted_values'],
            'sales_forms.*.conditions.*.field_name' => ['required', 'string', 'max:100'],
            'sales_forms.*.conditions.*.accepted_values' => ['required', 'array', 'min:1', 'max:20'],
            'sales_forms.*.conditions.*.accepted_values.*' => ['string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('sales_mode') !== DashboardSalesRuleService::MODE_CUSTOM) {
                return;
            }

            $campaignCode = trim((string) ($this->input('campaign_code') ?: $this->session()->get('campaign', '')));
            if ($campaignCode === '') {
                $validator->errors()->add('campaign_code', 'Select a campaign for the sales rules.');

                return;
            }

            $config = [
                'mode' => DashboardSalesRuleService::MODE_CUSTOM,
                'forms' => $this->input('sales_forms', []),
            ];
            foreach (app(DashboardSalesRuleService::class)->validationErrors($campaignCode, $config) as $error) {
                $validator->errors()->add($error['key'], $error['message']);
            }
        });
    }
}
