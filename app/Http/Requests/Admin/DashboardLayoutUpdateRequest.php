<?php

namespace App\Http\Requests\Admin;

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
            'section_order' => ['required', 'array', 'size:'.count($sectionKeys)],
            'section_order.*' => ['required', 'string', 'distinct', Rule::in($sectionKeys)],
            'visible_sections' => ['nullable', 'array', 'min:1'],
            'visible_sections.*' => ['required', 'string', 'distinct', Rule::in($sectionKeys)],
        ];
    }
}
