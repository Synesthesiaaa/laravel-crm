<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'form_code'     => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'name'          => ['required', 'string', 'max:255'],
            'table_name'    => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'color'         => ['nullable', 'string', 'max:50'],
            'icon'          => ['nullable', 'string', 'max:50'],
            'display_order' => ['nullable', 'integer'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'form_code.regex'   => 'Form code may only contain lowercase letters, numbers, and underscores.',
            'name.required'     => 'Form name is required.',
            'table_name.required' => 'Table name is required.',
            'table_name.regex'  => 'Table name may only contain lowercase letters, numbers, and underscores.',
        ];
    }
}
