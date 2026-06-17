<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VicidialSessionApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'api.vicidial.session.login' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_login' => ['nullable', 'string', 'max:32'],
                'phone_pass' => ['nullable', 'string', 'max:32'],
                'vd_login' => ['nullable', 'string', 'max:32'],
                'vd_pass' => ['nullable', 'string', 'max:32'],
                'blended' => ['nullable', 'boolean'],
                'ingroups' => ['nullable', 'array'],
                'ingroups.*' => ['string', 'max:32'],
            ],
            'api.vicidial.session.pause' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'value' => ['required', 'string', 'in:PAUSE,RESUME,pause,resume'],
            ],
            'api.vicidial.session.pause-code' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'pause_code' => ['required', 'string', 'max:6'],
            ],
            'api.vicidial.session.ingroups' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'action' => ['required', 'string', 'in:CHANGE,ADD,REMOVE,change,add,remove'],
                'ingroups' => ['nullable', 'array'],
                'ingroups.*' => ['string', 'max:32'],
                'blended' => ['nullable', 'boolean'],
            ],
            'api.vicidial.session.verify',
            'api.vicidial.session.iframe-url',
            'api.vicidial.session.logout',
            'api.vicidial.session.status' => [
                'campaign' => ['nullable', 'string', 'max:50'],
            ],
            default => [],
        };
    }
}
