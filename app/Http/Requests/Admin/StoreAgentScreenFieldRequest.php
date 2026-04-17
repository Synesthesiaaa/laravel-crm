<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentScreenFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50'],
            'field_key' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'field_label' => ['required', 'string', 'max:120'],
            'field_width' => ['nullable', 'in:full,half,third'],
        ];
    }
}
