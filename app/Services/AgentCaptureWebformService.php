<?php

namespace App\Services;

use App\Models\AgentScreenField;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentCaptureWebformService
{
    /**
     * @return array{campaign: Campaign, form: \App\Models\Form, fields: Collection<int, AgentScreenField>}|null
     */
    public function configuration(string $campaignCode): ?array
    {
        $campaign = Campaign::query()
            ->where('code', $campaignCode)
            ->where('is_active', true)
            ->with(['agentWebformForm' => fn (Relation $query) => $query->active()])
            ->first();

        if (
            ! $campaign
            || ! $campaign->agentWebformForm
            || ! $campaign->agentWebformForm->is_active
            || $campaign->agentWebformForm->campaign_code !== $campaign->code
        ) {
            return null;
        }

        return [
            'campaign' => $campaign,
            'form' => $campaign->agentWebformForm,
            'fields' => AgentScreenField::forCampaign($campaign->code)->ordered()->get(),
        ];
    }

    /**
     * @param  Collection<int, AgentScreenField>  $fields
     * @return array{lead_id: ?string, phone_number: ?string, fields: array<string, mixed>}
     */
    public function prefill(Collection $fields, Request $request): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (! in_array($field->direction, ['get', 'both'], true) || blank($field->vici_field)) {
                continue;
            }

            $value = $request->input($field->vici_field);

            if (is_scalar($value)) {
                $values[$field->field_key] = $value;
            }
        }

        return [
            'lead_id' => $this->scalarInput($request, 'lead_id'),
            'phone_number' => $this->scalarInput($request, 'phone_number'),
            'fields' => $values,
        ];
    }

    /**
     * @param  Collection<int, AgentScreenField>  $fields
     */
    public function vicidialUrl(string $campaignCode, Collection $fields): string
    {
        $parameters = [
            'lead_id' => '--A--lead_id--B--',
            'phone_number' => '--A--phone_number--B--',
        ];
        $mapped = [];

        foreach ($fields as $field) {
            if (! in_array($field->direction, ['get', 'both'], true) || blank($field->vici_field)) {
                continue;
            }

            $source = (string) $field->vici_field;

            if (isset($mapped[$source])) {
                continue;
            }

            $mapped[$source] = true;
            $parameters[$source] = '--A--'.$source.'--B--';
        }

        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return 'VAR'.url('/agent-webforms/'.$campaignCode).'?'.$query;
    }

    private function scalarInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : null;
    }
}
