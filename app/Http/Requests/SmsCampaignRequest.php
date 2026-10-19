<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsCampaignRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('scheduled_at')) && $this->filled('scheduled_at')) {
            try {
                $this->merge(['scheduled_at' => \Carbon\Carbon::parse($this->input('scheduled_at'), 'Asia/Manila')->utc()->toIso8601String()]);
            } catch (\Throwable) {
                // The date rule reports invalid input without converting it into a schedule.
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150', 'description' => 'nullable|string|max:2000',
            'body' => 'required|string|max:1000', 'category' => ['required', Rule::in(['marketing', 'operational'])],
            'sms_gateway_id' => 'required|integer|exists:sms_gateways,id',
            'channel_ids' => 'required|array|min:1|max:32',
            'channel_ids.*' => ['required', 'integer', 'distinct', Rule::exists('sms_channels', 'id')->where('sms_gateway_id', $this->input('sms_gateway_id'))],
            'scheduled_at' => 'nullable|date|after:now',
            'quiet_start' => 'required|integer|min:0|max:23', 'quiet_end' => 'required|integer|min:0|max:23|different:quiet_start',
        ];
    }
}
