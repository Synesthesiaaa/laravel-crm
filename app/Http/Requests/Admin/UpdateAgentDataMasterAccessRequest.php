<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentDataMasterAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'own_submissions_only' => ['sometimes', 'boolean'],
            'form_ids' => [Rule::requiredIf($this->boolean('enabled')), 'array', 'max:1000'],
            'form_ids.*' => ['required', 'integer', 'distinct', Rule::exists('forms', 'id')->where(fn ($query) => $query
                ->where('campaign_code', $this->route('campaign'))->where('is_active', true)->whereNull('deleted_at'))],
        ];
    }

    public function messages(): array
    {
        return ['form_ids.required' => 'Select at least one active form before enabling agent access.'];
    }
}
