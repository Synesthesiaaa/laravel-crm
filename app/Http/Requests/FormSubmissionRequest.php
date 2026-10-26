<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Repositories\FormFieldRepository;
use App\Support\FormFieldValidationRules;
use App\Support\FormFieldVisibility;
use Illuminate\Foundation\Http\FormRequest;

class FormSubmissionRequest extends FormRequest
{
    /** @var list<string> */
    private array $hiddenFormFields = [];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(FormFieldRepository $formFieldRepository): array
    {
        $campaign = $this->string('campaign')->trim()->toString();
        $formType = $this->string('form_type')->trim()->toString();

        $rules = [
            'campaign' => ['required', 'string', 'max:50'],
            'form_type' => ['required', 'string', 'max:50'],
            'date' => ['required', 'string', 'date'],
            'request_id' => ['nullable', 'string', 'max:255'],
        ];

        if ($campaign !== '' && $formType !== '') {
            $fields = $formFieldRepository->getFieldsForForm($campaign, $formType);
            foreach ($fields as $field) {
                $name = $field->field_name;
                if (in_array($name, ['date', 'request_id', 'agent', 'id', 'created_at', 'updated_at'], true)) {
                    continue;
                }
                $rules += FormFieldValidationRules::for($field, in_array($name, $this->hiddenFormFields, true));
            }
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];
        foreach ($this->all() as $key => $value) {
            if (is_array($value)) {
                $merged[$key] = $value;
            } elseif (is_string($value)) {
                $merged[$key] = trim($value);
            } else {
                $merged[$key] = $value;
            }
        }

        $campaign = is_string($merged['campaign'] ?? null) ? trim($merged['campaign']) : '';
        $formType = is_string($merged['form_type'] ?? null) ? trim($merged['form_type']) : '';
        if ($this->user()?->role === User::ROLE_AGENT) {
            $merged['date'] = now()->toDateString();
        }

        if ($campaign !== '' && $formType !== '') {
            $repo = app(FormFieldRepository::class);
            $fields = $repo->getFieldsForForm($campaign, $formType);
            foreach ($fields as $field) {
                if (! FormFieldVisibility::isVisible($field->visibility, $merged)) {
                    $this->hiddenFormFields[] = $field->field_name;
                }
            }

            foreach ($fields as $field) {
                if (in_array($field->field_name, $this->hiddenFormFields, true)) {
                    continue;
                }
                if ($field->field_type === 'multiselect' && ! array_key_exists($field->field_name, $merged)) {
                    $merged[$field->field_name] = [];
                }
            }
        }

        $this->merge($merged);
    }
}
