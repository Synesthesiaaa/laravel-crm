<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'campaign_code'          => ['required', 'string', 'max:50'],
            'lead_id'                => ['nullable', 'integer'],
            'phone_number'           => ['nullable', 'string', 'max:30'],
            'disposition_code'       => ['required', 'string', 'max:50'],
            'disposition_label'      => ['required', 'string', 'max:255'],
            'remarks'                => ['nullable', 'string', 'max:2000'],
            'call_duration_seconds'  => ['nullable', 'integer', 'min:0'],
            'lead_data_json'         => ['nullable', 'array'],
        ];
    }
}
