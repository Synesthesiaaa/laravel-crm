<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispositionCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50'],
            'code'          => ['required', 'string', 'max:50'],
            'label'         => ['required', 'string', 'max:255'],
            'sort_order'    => ['nullable', 'integer'],
        ];
    }
}
