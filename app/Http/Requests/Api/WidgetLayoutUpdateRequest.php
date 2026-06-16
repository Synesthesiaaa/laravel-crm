<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WidgetLayoutUpdateRequest extends FormRequest
{
    private const ALLOWED_WIDGETS = ['softphone', 'quick_form'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'widget' => $this->route('widget'),
        ]);
    }

    public function rules(): array
    {
        return [
            'widget' => ['required', 'string', Rule::in(self::ALLOWED_WIDGETS)],
            'layout' => ['required', 'array'],
            'layout.x' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'layout.y' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'layout.width' => ['nullable', 'numeric', 'min:200', 'max:2400'],
            'layout.height' => ['nullable', 'numeric', 'min:120', 'max:2400'],
            'layout.open' => ['nullable', 'boolean'],
            'layout.controlsHeight' => ['nullable', 'numeric', 'min:120', 'max:1200'],
            'layout.z' => ['nullable', 'integer', 'min:1', 'max:999'],
            'layout.formType' => ['nullable', 'string', 'max:100'],
            'layout.campaign' => ['nullable', 'string', 'max:100'],
        ];
    }
}
