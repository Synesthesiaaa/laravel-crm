<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50', 'exists:campaigns,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable', 'boolean'],
            'reset_time' => ['nullable', 'date'],
            'display_order' => ['nullable', 'integer'],
        ];
    }
}
