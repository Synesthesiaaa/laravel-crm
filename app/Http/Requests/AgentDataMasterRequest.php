<?php

namespace App\Http\Requests;

use App\Services\AgentDataMasterAccessService;
use Illuminate\Foundation\Http\FormRequest;

class AgentDataMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(AgentDataMasterAccessService::class)->canView($this->user(), (string) $this->session()->get('campaign'));
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'column_filters' => ['sometimes', 'array', 'max:100'],
            'column_filters.*' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
