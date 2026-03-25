<?php

namespace App\Http\Requests;

use App\Repositories\FormFieldRepository;
use Illuminate\Foundation\Http\FormRequest;

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
        $this->merge(array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $this->all()));
    }
}
