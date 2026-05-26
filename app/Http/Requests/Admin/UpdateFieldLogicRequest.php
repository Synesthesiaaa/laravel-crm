<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldLogicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'field_label' => ['required', 'string', 'max:255'],
            'field_name' => ['sometimes', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'field_type' => ['sometimes', 'in:text,textarea,number,date,select,multiselect,percentage'],
            'options' => ['nullable', 'string', 'max:65535'],
            'is_required' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer'],
            'field_width' => ['nullable', 'in:full,half,third'],
            'visibility' => ['nullable', 'array'],
            'visibility.field' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'visibility.operator' => ['nullable', 'in:equals,not_equals,in,not_in'],
            'visibility.values' => ['nullable', 'array'],
            'visibility.values.*' => ['string', 'max:120'],
        ];
    }
}
