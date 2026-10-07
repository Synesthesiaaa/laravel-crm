<?php

namespace App\Http\Requests\Admin;

use App\Models\Campaign;
use App\Services\DashboardLayoutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardLayoutUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $sectionKeys = array_keys(DashboardLayoutService::sectionDefinitions());

        return [
            'amounts' => ['sometimes', 'array:enabled,total,change,charts,tables'],
            'amounts.enabled' => ['sometimes', 'boolean'],
            'amounts.total' => ['sometimes', 'boolean'],
            'amounts.change' => ['sometimes', 'boolean'],
            'amounts.charts' => ['sometimes', 'boolean'],
            'amounts.tables' => ['sometimes', 'boolean'],
            'section_order' => ['required', 'array', 'size:'.count($sectionKeys)],
            'section_order.*' => ['required', 'string', 'distinct', Rule::in($sectionKeys)],
            'visible_sections' => ['nullable', 'array', 'min:1'],
            'visible_sections.*' => ['required', 'string', 'distinct', Rule::in($sectionKeys)],
            'campaign_code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::exists(Campaign::class, 'code')->where(static fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
