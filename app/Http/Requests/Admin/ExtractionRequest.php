<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeamLeader() ?? false;
    }

    public function rules(): array
    {
        return [
            'data_type'  => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
