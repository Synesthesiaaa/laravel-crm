<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePauseCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeamLeader() ?? false;
    }

    public function rules(): array
    {
        $pauseCode = $this->route('pauseCode');
        $id = $pauseCode instanceof \App\Models\PauseCode ? $pauseCode->getKey() : (int) $pauseCode;

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('pause_codes', 'code')->ignore($id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
