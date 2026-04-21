<?php

namespace App\Http\Requests;

use App\Repositories\FormFieldRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormSubmissionRequest extends FormRequest
{
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
                if ($field->field_type === 'multiselect') {
                    if ($field->is_required) {
                        $rules[$name] = ['required', 'array', 'min:1'];
                    } else {
                        $rules[$name] = ['nullable', 'array'];
                    }
                    $allowed = $field->optionValues();
                    if ($allowed !== []) {
                        $rules[$name.'.*'] = ['string', 'max:255', Rule::in($allowed)];
                    } else {
                        $rules[$name.'.*'] = ['string', 'max:255'];
                    }

                    continue;
                }
                $fieldRules = [];
                if ($field->is_required) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }
                switch ($field->field_type) {
                    case 'number':
                        $fieldRules[] = 'numeric';

                        break;
                    case 'date':
                        $fieldRules[] = 'date';

                        break;
                    case 'textarea':
                        $fieldRules[] = 'string';
                        $fieldRules[] = 'max:65535';

                        break;
                    case 'select':
                        $fieldRules[] = 'string';
                        $fieldRules[] = 'max:255';
                        $allowed = $field->optionValues();
                        if ($allowed !== []) {
                            $fieldRules[] = Rule::in($allowed);
                        }

                        break;
                    default:
                        $fieldRules[] = 'string';
                        $fieldRules[] = 'max:255';

                        break;
                }
                $rules[$name] = $fieldRules;
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
        if ($campaign !== '' && $formType !== '') {
            $repo = app(FormFieldRepository::class);
            foreach ($repo->getFieldsForForm($campaign, $formType) as $field) {
                if ($field->field_type === 'multiselect' && ! array_key_exists($field->field_name, $merged)) {
                    $merged[$field->field_name] = [];
                }
            }
        }

        $this->merge($merged);
    }
}
