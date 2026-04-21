<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reset_time' => ['nullable', 'date'],
            'display_order' => ['nullable', 'integer'],
        ];
    }
}
