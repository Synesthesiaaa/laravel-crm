<?php

namespace App\Http\Requests\Admin;

use App\Models\Form;
use App\Services\DataRetentionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertDataRetentionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'form_id' => [
                'required',
                'integer',
                Rule::exists('forms', 'id')->where(fn (Builder $query): Builder => $query->where('is_active', true)),
            ],
            'from_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:to_date'],
            'to_date' => ['required', 'date_format:Y-m-d'],
            'deletion_mode' => ['required', Rule::in(['whole_record', 'selected_fields'])],
            'selected_fields' => [
                'exclude_unless:deletion_mode,selected_fields',
                'required',
                'array',
                'min:1',
            ],
            'selected_fields.*' => ['string', 'distinct'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->count() > 0 || $this->input('deletion_mode') !== 'selected_fields') {
                return;
            }

            $form = Form::query()
                ->where('is_active', true)
                ->find($this->integer('form_id'));

            if ($form === null) {
                return;
            }

            $selectedFields = $this->input('selected_fields', []);
            $eligibleFields = app(DataRetentionService::class)
                ->eligibleFields($form)
                ->pluck('field_name')
                ->all();

            if (array_diff($selectedFields, $eligibleFields) !== []) {
                $validator->errors()->add(
                    'selected_fields',
                    'Selected fields must belong to the selected form and support safe clearing.',
                );
            }
        });
    }
}
