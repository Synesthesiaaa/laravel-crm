<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StageEmailAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:email_campaigns,id'],
            'source' => ['required', 'in:manual,csv,xlsx,crm,segment'],
            'duplicate_policy' => ['sometimes', 'in:exclude,reject'],
            'file' => ['required_if:source,csv,xlsx', 'nullable', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
            'worksheet' => ['sometimes', 'integer', 'between:1,10'],
            'manual_recipients' => ['required_if:source,manual', 'nullable', 'string', 'max:262144'],
            'crm_model' => ['required_if:source,crm', 'nullable', 'in:hopper,capture'],
            'campaign_code' => ['required_if:source,crm', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'email_field' => ['required_if:source,crm', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'name_field' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'segment_id' => ['required_if:source,segment', 'nullable', 'integer', 'exists:email_segments,id'],
            'assert_permission' => ['sometimes', 'boolean'],
            'permission_source' => ['required_if:assert_permission,1', 'nullable', 'string', 'max:500'],
            'permission_at' => ['required_if:assert_permission,1', 'nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
