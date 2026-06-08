<?php

namespace App\Services\Telephony;

use Illuminate\Http\Request;

/**
 * VICIdial / dialer campaign is stored separately from CRM session('campaign') so the softphone
 * does not follow CRM campaign switches unless the user explicitly chooses a softphone campaign.
 */
final class TelephonyCampaignResolver
{
    /**
     * Softphone-selected campaign, then the user's default dialer campaign.
     */
    public static function forRequest(Request $request): string
    {
        $vic = $request->session()->get('vicidial_campaign');
        if (is_string($vic) && $vic !== '') {
            return $vic;
        }

        $defaultCampaign = $request->user()?->default_campaign;
        if (is_string($defaultCampaign) && $defaultCampaign !== '') {
            return $defaultCampaign;
        }

        return 'mbsales';
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
