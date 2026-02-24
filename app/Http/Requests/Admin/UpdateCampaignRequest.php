<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $campaignId = $this->route('campaign')?->id ?? 0;
        return [
            'code'          => ['required', 'string', 'max:50', "unique:campaigns,code,{$campaignId}", 'regex:/^[a-z0-9_]+$/'],
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'color'         => ['nullable', 'string', 'max:50'],
            'display_order' => ['nullable', 'integer'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Campaign code is required.',
            'code.unique'   => 'That campaign code is already in use.',
            'code.regex'    => 'Campaign code may only contain lowercase letters, numbers, and underscores.',
            'name.required' => 'Campaign name is required.',
        ];
    }
}
