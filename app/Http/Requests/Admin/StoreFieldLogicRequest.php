<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldLogicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50'],
            'form_type'     => ['required', 'string', 'max:50'],
            'field_name'    => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'field_label'   => ['required', 'string', 'max:255'],
            'field_type'    => ['required', 'in:text,textarea,number,date,select'],
            'is_required'   => ['nullable', 'boolean'],
            'field_order'   => ['nullable', 'integer'],
            'field_width'   => ['nullable', 'in:full,half,third'],
        ];
    }
}
