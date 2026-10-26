<?php

namespace App\Support;

use App\Models\FormField;
use Illuminate\Validation\Rule;

class FormFieldValidationRules
{
    /** @return array<string, array<int, mixed>> */
    public static function for(FormField $field, bool $hidden = false): array
    {
        $name = (string) $field->field_name;
        if ($hidden) {
            return [$name => ['nullable']];
        }

        if ($field->field_type === 'multiselect') {
            $rules = [$name => $field->is_required ? ['required', 'array', 'min:1'] : ['nullable', 'array']];
            $itemRules = ['string', 'max:255'];
            $allowed = $field->optionValues();
            if ($allowed !== []) {
                $itemRules[] = Rule::in($allowed);
            }
            $rules[$name.'.*'] = $itemRules;

            return $rules;
        }

        $fieldRules = [$field->is_required ? 'required' : 'nullable'];
        switch ((string) $field->field_type) {
            case 'number':
                if (FormFieldNumber::isIdentifierLike($name)) {
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                } else {
                    $fieldRules[] = static function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! FormFieldNumber::isValidDecimal($value)) {
                            $fail("The {$attribute} field must be a decimal with up to two fractional digits.");
                        }
                    };
                }

                break;
            case 'date':
                $fieldRules[] = 'date';

                break;
            case 'percentage':
                $fieldRules[] = static function (string $attribute, mixed $value, \Closure $fail): void {
                    $number = PercentageValue::numeric($value);
                    if (! FormFieldNumber::isValidDecimal($number) || (float) $number < 0 || (float) $number > 100) {
                        $fail("The {$attribute} field must be a percentage between 0 and 100.");
                    }
                };

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

        return [$name => $fieldRules];
    }
}
