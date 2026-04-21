<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'list_id' => ['required', 'integer', 'exists:lead_lists,id'],
            'phone_number' => ['required', 'string', 'max:32'],
            'vendor_lead_code' => ['nullable', 'string', 'max:50'],
            'source_id' => ['nullable', 'string', 'max:50'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'alt_phone' => ['nullable', 'string', 'max:32'],
            'title' => ['nullable', 'string', 'max:40'],
            'first_name' => ['nullable', 'string', 'max:60'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'address1' => ['nullable', 'string', 'max:100'],
            'address2' => ['nullable', 'string', 'max:100'],
            'address3' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:50'],
            'province' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:10'],
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:100'],
            'security_phrase' => ['nullable', 'string', 'max:100'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'max:20'],
            'enabled' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
