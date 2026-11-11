<?php

namespace App\Http\Requests\Admin;

use App\Services\Email\TemplateComposer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('design_json')) && strlen($this->input('design_json')) <= 500000) {
            $decoded = json_decode($this->input('design_json'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['design_json' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255', 'not_regex:/[\r\n\x00]/'],
            'body_format' => ['required', 'in:text,html'],
            'html_body' => ['required', 'string', 'max:200000'],
            'design_json' => ['nullable', 'array'],
            'pdf_enabled' => ['prohibited'],
            'pdf_body' => ['prohibited'],
            'pdf_password' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $design = json_encode($this->input('design_json'));
            if ($design === false || strlen($design) > 500000) {
                $validator->errors()->add('design_json', 'The visual design exceeds 500 KB.');
            }
            if ($this->input('body_format') === 'text' && mb_strlen((string) $this->input('html_body')) > 50000) {
                $validator->errors()->add('html_body', 'Plain-text messages must not exceed 50,000 characters.');
            }
            if (! $validator->errors()->has('subject')) {
                app(TemplateComposer::class)->validateVariables((string) $this->input('subject'));
            }
        }];
    }
}
