<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150', 'host' => ['required', 'ipv4', Rule::in(config('sms.allowed_ips'))],
            'port' => 'required|integer|min:1|max:65535', 'username' => 'required|string|max:100',
            'password' => ($this->route('gateway') ? 'nullable' : 'required').'|string|max:255',
            'firmware' => 'required|string|max:100', 'enabled' => 'nullable|boolean',
            'verified' => 'sometimes|accepted', 'inbound_verified' => 'nullable|boolean',
            'verification_note' => 'nullable|required_if:verified,1|string|min:30|max:2000',
        ];
    }
}
