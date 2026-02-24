<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentScreenFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'field_label' => ['required', 'string', 'max:120'],
            'field_width' => ['nullable', 'in:full,half,third'],
        ];
    }
}
