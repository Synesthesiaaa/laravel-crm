<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentScreenFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $field = $this->route('field');

        return [
            'field_key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('agent_screen_fields', 'field_key')
                    ->ignore($field?->id)
                    ->where(fn ($query) => $query->where('campaign_code', $field?->campaign_code)),
            ],
            'field_label' => ['required', 'string', 'max:120'],
            'vici_field' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'direction' => ['nullable', 'in:get,post,both,none'],
            'field_type' => ['nullable', 'in:text,number,email,tel,date,textarea,select,checkbox'],
            'options' => ['nullable', 'string', 'max:2000'],
            'placeholder' => ['nullable', 'string', 'max:120'],
            'is_required' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer', 'min:0'],
            'field_width' => ['nullable', 'in:full,half,third'],
        ];
    }
}
