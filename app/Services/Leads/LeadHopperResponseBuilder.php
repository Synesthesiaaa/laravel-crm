<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadHopper;
use App\Support\LeadPayloadBuilder;

class LeadHopperResponseBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function toApiArray(LeadHopper $hopper): array
    {
        $lead = $hopper->lead_pk ? Lead::find($hopper->lead_pk) : null;
        $hopperCustom = is_array($hopper->custom_data) ? $hopper->custom_data : [];

        $fields = $lead !== null
            ? LeadPayloadBuilder::buildFieldsForAgent($lead, $hopperCustom)
            : array_map(
                fn ($v) => is_scalar($v) ? (string) $v : json_encode($v),
                array_filter($hopperCustom, fn ($v) => $v !== null && $v !== '')
            );

        return [
            'lead_pk' => $hopper->lead_pk,
            'lead_id' => $hopper->lead_id,
            'phone_number' => $hopper->phone_number,
            'client_name' => $hopper->client_name,
            'custom_data' => $hopper->custom_data ?? [],
            'fields' => $fields,
        ];
    }
}
