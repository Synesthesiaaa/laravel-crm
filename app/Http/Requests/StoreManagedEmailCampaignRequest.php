<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedEmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email_template_id' => ['required', 'integer', Rule::exists('email_templates', 'id')->where(function ($query): void {
                $query->where('pdf_enabled', false);
                if (! $this->user()->isSuperAdmin()) {
                    $query->where('created_by_user_id', $this->user()->id);
                }
            })],
            'batch_size' => ['required', 'integer', 'between:1,100'],
            'delay_seconds' => ['required', 'integer', 'between:1,3600'],
            'tracking_opens' => ['sometimes', 'boolean'],
            'tracking_clicks' => ['sometimes', 'boolean'],
            'confirmed_tracking' => ['required_if:tracking_opens,1', 'required_if:tracking_clicks,1', 'accepted_if:tracking_opens,1', 'accepted_if:tracking_clicks,1'],
        ];
    }
}
