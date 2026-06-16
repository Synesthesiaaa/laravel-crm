<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CallApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'api.call.dial' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['required', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'phone_code' => ['nullable', 'string', 'max:5'],
            ],
            'api.call.hangup' => [
                'session_id' => ['nullable', 'integer'],
                'campaign' => ['nullable', 'string', 'max:50'],
            ],
            'api.call.predictive-dial', 'api.call.status' => [
                'campaign' => ['nullable', 'string', 'max:50'],
            ],
            default => [],
        };
    }
}
