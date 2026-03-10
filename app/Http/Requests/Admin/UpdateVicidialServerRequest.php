<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVicidialServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50'],
            'server_name'   => ['required', 'string', 'max:100'],
            'api_url'       => ['required', 'url', 'max:500'],
            'db_host'       => ['required', 'string', 'max:255'],
            'db_username'   => ['required', 'string', 'max:100'],
            'db_password'   => ['nullable', 'string', 'max:255'],
            'db_name'       => ['nullable', 'string', 'max:100'],
            'db_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'api_user'      => ['nullable', 'string', 'max:100'],
            'api_pass'      => ['nullable', 'string', 'max:255'],
            'source'        => ['nullable', 'string', 'max:100'],
            'is_active'     => ['nullable', 'boolean'],
            'is_default'    => ['nullable', 'boolean'],
            'priority'      => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
