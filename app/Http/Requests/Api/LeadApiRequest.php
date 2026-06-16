<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LeadApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'api.leads.search' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['required', 'string', 'max:32'],
            ],
            'api.leads.info' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'phone_number' => ['nullable', 'string', 'max:32'],
            ],
            'api.leads.field' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['required', 'integer'],
                'field_name' => ['required', 'string', 'max:100'],
            ],
            'api.leads.hydrate' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'phone_number' => ['nullable', 'string', 'max:32'],
            ],
            'api.leads.add' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['required', 'string', 'max:32'],
                'phone_code' => ['nullable', 'string', 'max:4'],
                'list_id' => ['nullable', 'string', 'max:12'],
            ],
            'api.leads.update' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'vendor_lead_code' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['nullable', 'string', 'max:32'],
            ],
            'api.leads.switch' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['required', 'integer'],
            ],
            'api.leads.update-fields' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'fields' => ['required', 'array'],
            ],
            default => [],
        };
    }
}
