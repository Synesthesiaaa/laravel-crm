<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_SUPER_ADMIN;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email_template_id' => ['required', 'integer', 'exists:email_templates,id'],
            'recipient_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'batch_size' => ['required', 'integer', 'between:1,100'],
            'delay_seconds' => ['required', 'integer', 'between:1,3600'],
            'confirmed_permission' => ['accepted'],
            'purpose' => ['sometimes', 'in:marketing,transactional,payroll'],
            'attachment_mode' => ['sometimes', 'in:none,shared,recipient_specific,shared_and_recipient_specific'],
            'personal_pdfs' => ['sometimes', 'array', 'max:'.config('email_campaigns.max_files')],
            'personal_pdfs.*' => ['file', 'max:'.(int) ceil(config('email_campaigns.max_file_bytes') / 1024)],
            'shared_pdfs' => ['sometimes', 'array', 'max:'.config('email_campaigns.max_files')],
            'shared_pdfs.*' => ['file', 'max:'.(int) ceil(config('email_campaigns.max_file_bytes') / 1024)],
            'document_zip' => ['nullable', 'file', 'max:'.(int) ceil(config('email_campaigns.max_total_bytes') / 1024)],
        ];
    }
}
