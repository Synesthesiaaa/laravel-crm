<?php

namespace App\Services\Telephony;

use Illuminate\Http\Request;

/**
 * VICIdial / dialer campaign is stored separately from CRM session('campaign') so the softphone
 * does not overwrite the user's login campaign (forms, records, dispositions, etc.).
 */
final class TelephonyCampaignResolver
{
    /**
     * Softphone-selected campaign, or CRM campaign when the user has not chosen a dialer campaign.
     */
    public static function forRequest(Request $request): string
    {
        $crm = (string) $request->session()->get('campaign', 'mbsales');
        $vic = $request->session()->get('vicidial_campaign');

        return (is_string($vic) && $vic !== '') ? $vic : $crm;
    }

    /**
     * Prefer an explicit campaign from query/body when non-empty; otherwise session telephony default.
     */
    public static function resolve(Request $request, ?string $explicit): string
    {
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        return self::forRequest($request);
    }
}
