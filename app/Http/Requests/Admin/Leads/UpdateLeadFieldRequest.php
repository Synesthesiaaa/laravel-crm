<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'field_label' => ['required', 'string', 'max:120'],
            'field_type' => ['required', 'in:text,number,email,date,select,textarea'],
            'field_options' => ['nullable', 'array'],
            'visible' => ['nullable', 'boolean'],
            'exportable' => ['nullable', 'boolean'],
            'importable' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer'],
        ];
    }
}
