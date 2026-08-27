<?php

namespace App\Http\Requests\Admin;

use App\Models\Campaign;
use App\Models\VicidialServer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCampaignVicidialMappingRequest extends FormRequest
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
            'vicidial_server_id' => ['required', 'integer', 'exists:vicidial_servers,id'],
            'vicidial_campaign_codes' => ['required', 'array', 'min:1'],
            'vicidial_campaign_codes.*' => ['required', 'string', 'max:50', 'distinct', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $campaign = $this->route('campaign');
            $serverId = (int) $this->input('vicidial_server_id');
            if (! $campaign instanceof Campaign || $serverId === 0) {
                return;
            }

            $serverBelongsToCampaign = VicidialServer::query()
                ->whereKey($serverId)
                ->where('campaign_code', $campaign->code)
                ->exists();
            if (! $serverBelongsToCampaign) {
                $validator->errors()->add(
                    'vicidial_server_id',
                    'The selected VICIdial server is not assigned to this CRM campaign.',
                );
            }
        });
    }
}
