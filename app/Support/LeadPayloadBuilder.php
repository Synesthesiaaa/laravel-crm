<?php

namespace App\Support;

use App\Models\Lead;

/**
 * Builds a flat key => scalar map for agent screen prefill (standard columns + custom_fields + hopper overlay).
 */
class LeadPayloadBuilder
{
    /**
     * @param  array<string, mixed>|null  $hopperCustomData  Overlay from lead_hopper.custom_data (authoritative for keys present).
     * @return array<string, string>
     */
    public static function buildFieldsForAgent(Lead $lead, ?array $hopperCustomData = null): array
    {
        $standardKeys = [
            'id', 'list_id', 'campaign_code', 'vendor_lead_code', 'source_id', 'phone_code',
            'phone_number', 'alt_phone', 'title', 'first_name', 'middle_initial', 'last_name',
            'address1', 'address2', 'address3', 'city', 'state', 'province', 'postal_code', 'country',
            'gender', 'date_of_birth', 'email', 'security_phrase', 'comments', 'status', 'enabled',
            'called_count', 'last_called_at', 'last_local_call_time', 'user',
        ];

        $out = [];
        foreach ($standardKeys as $key) {
            $val = $lead->getAttribute($key);
            if ($val === null) {
                continue;
            }
            if ($val instanceof \DateTimeInterface) {
                $out[$key] = $val->format($key === 'date_of_birth' ? 'Y-m-d' : 'Y-m-d H:i:s');
            } elseif (is_bool($val)) {
                $out[$key] = $val ? '1' : '0';
            } elseif (is_scalar($val)) {
                $out[$key] = (string) $val;
            }
        }

        $custom = is_array($lead->custom_fields) ? $lead->custom_fields : [];
        foreach ($custom as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $out[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        if (is_array($hopperCustomData)) {
            foreach ($hopperCustomData as $k => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $out[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function snapshot(Lead $lead): array
    {
        return self::buildFieldsForAgent($lead);
    }
}
