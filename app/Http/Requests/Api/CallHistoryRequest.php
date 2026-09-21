<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CallHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'campaign' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'agent' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:80'],
            'disposition' => ['nullable', 'string', 'max:255'],
            'vicidial_campaign' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', 'in:INBOUND,OUTBOUND'],
            'sort' => ['nullable', 'in:called_at,agent,duration,status,vicidial_campaign'],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
        ];
    }
}
