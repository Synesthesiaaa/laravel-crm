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
            'field_name'  => ['sometimes', 'string', 'max:80'],
            'is_required' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer'],
            'field_width' => ['nullable', 'in:full,half,third'],
        ];
    }
}
