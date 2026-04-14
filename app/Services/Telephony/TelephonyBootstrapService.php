<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Http\Request;

class TelephonyBootstrapService
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    /**
     * Stage one-time telephony bootstrap payload for the next authenticated page load.
     */
    public function storeBootstrapPayload(Request $request, User $user): void
    {
        $request->session()->forget('telephony_bootstrap');

        if (! config('vicidial.auto_bootstrap_on_crm_login', false)) {
            return;
        }

        if (! $user->auto_vici_login) {
            return;
        }

        if (empty($user->vici_user) || empty($user->vici_pass) || empty($user->extension)) {
            return;
        }

        $campaigns = $this->campaignService->getCampaigns();
        $sessionCampaign = $request->session()->get('campaign');

        $campaign = null;
        if (is_string($sessionCampaign) && $sessionCampaign !== '' && isset($campaigns[$sessionCampaign])) {
            $campaign = $sessionCampaign;
        } elseif (is_string($user->default_campaign) && $user->default_campaign !== '' && isset($campaigns[$user->default_campaign])) {
            $campaign = $user->default_campaign;
        } else {
            $first = array_key_first($campaigns);
            $campaign = $first ?: 'mbsales';
        }

        $request->session()->put('telephony_bootstrap', [
            'campaign' => (string) $campaign,
            'phone_login' => (string) $user->extension,
            'blended' => (bool) ($user->default_blended ?? true),
            'ingroups' => $this->normalizeIngroups((string) ($user->default_ingroups ?? '')),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeIngroups(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];

        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $parts)));
    }
}
