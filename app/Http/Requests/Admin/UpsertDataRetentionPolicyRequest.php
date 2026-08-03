<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'cutoff_date' => ['required', 'date_format:Y-m-d'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
