<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAgentScreenWebformRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50', 'exists:campaigns,code'],
            'agent_webform_form_id' => [
                'nullable',
                'integer',
                Rule::exists('forms', 'id')->where(function (Builder $query): void {
                    $query->where('campaign_code', (string) $this->input('campaign_code'))
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
