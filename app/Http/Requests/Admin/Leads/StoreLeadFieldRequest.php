<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50', 'exists:campaigns,code'],
            'field_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'field_label' => ['required', 'string', 'max:120'],
            'field_type' => ['required', 'in:text,number,email,date,select,textarea'],
            'field_options' => ['nullable', 'array'],
            'visible' => ['nullable', 'boolean'],
            'exportable' => ['nullable', 'boolean'],
            'importable' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'field_key.regex' => 'Field key may only contain lowercase letters, numbers, and underscores.',
        ];
    }
}
