<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_vici_login' => $this->boolean('auto_vici_login'),
            'default_blended' => $this->boolean('default_blended'),
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
            'role' => ['required', 'in:Super Admin,Admin,Team Leader,Agent'],
            'vici_user' => ['nullable', 'string', 'max:80'],
            'extension' => ['nullable', 'string', 'max:50'],
            'vici_pass' => ['nullable', 'string', 'max:255'],
            'sip_password' => ['nullable', 'string', 'min:4', 'max:255'],
            'default_campaign' => ['nullable', 'string', 'max:50'],
            'auto_vici_login' => ['sometimes', 'boolean'],
            'default_blended' => ['sometimes', 'boolean'],
            'default_ingroups' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'username.unique' => 'That username is already in use.',
            'full_name.required' => 'Full name is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex' => 'Password must contain uppercase, lowercase, and a number.',
        ];
    }
}
