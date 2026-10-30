<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['csv' => ['required', 'file', 'extensions:csv', 'max:20480'], 'allow_privileged_roles' => ['sometimes', 'boolean']];
    }
}
