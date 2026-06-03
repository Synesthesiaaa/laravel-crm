<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\NormalizesVisibilityInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreFieldLogicRequest extends FormRequest
{
    use NormalizesVisibilityInput;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50'],
            'form_type' => ['required', 'string', 'max:50'],
            'field_name' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'field_label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,textarea,number,date,select,multiselect,percentage'],
            'is_required' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer'],
            'field_width' => ['nullable', 'in:full,half,third'],
            'options' => ['nullable', 'string', 'max:65535'],
            'visibility' => ['nullable', 'array'],
            'visibility.field' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_]+$/'],
            'visibility.operator' => ['nullable', 'in:equals,not_equals,in,not_in'],
            'visibility.values' => ['nullable', 'array'],
            'visibility.values.*' => ['nullable', 'string', 'max:120'],
        ];
    }
}
