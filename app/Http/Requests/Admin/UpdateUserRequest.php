<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? 0;
        return [
            'username'  => ['required', 'string', 'max:80', "unique:users,username,{$userId}"],
            'full_name' => ['required', 'string', 'max:255'],
            'role'      => ['required', 'in:Super Admin,Admin,Team Leader,Agent'],
            'vici_user'    => ['nullable', 'string', 'max:80'],
            'extension'    => ['nullable', 'string', 'max:50'],
            'vici_pass'    => ['nullable', 'string', 'max:255'],
            'sip_password' => ['nullable', 'string', 'min:4', 'max:255'],
            'password'     => ['nullable', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required'  => 'Username is required.',
            'username.unique'    => 'That username is already in use.',
            'full_name.required' => 'Full name is required.',
            'password.min'       => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex'     => 'Password must contain uppercase, lowercase, and a number.',
        ];
    }
}
