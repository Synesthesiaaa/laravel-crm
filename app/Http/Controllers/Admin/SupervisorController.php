<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispositionCode;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function index(Request $request): View
    {
        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaign($request, $campaigns);

        $dispositionCodes = DispositionCode::where(function ($q) use ($campaign) {
            $q->where('campaign_code', $campaign)->orWhereNull('campaign_code');
        })->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.supervisor', [
            'dispositionCodes' => $dispositionCodes,
            'supervisorCampaign' => $campaign,
            'supervisorCampaigns' => $campaigns,
        ]);
    }

    /**
     * Resolve the CRM campaign displayed by Supervisor without changing the
     * separate VICIdial campaign stored in the session.
     *
     * @param  array<string, array<string, mixed>>  $campaigns
     */
    private function resolveCampaign(Request $request, array $campaigns): string
    {
        $requested = trim((string) $request->query('campaign', ''));
        if ($requested !== '' && isset($campaigns[$requested])) {
            return $requested;
        }

        $sessionCampaign = trim((string) $request->session()->get('campaign', ''));
        if ($sessionCampaign !== '' && isset($campaigns[$sessionCampaign])) {
            return $sessionCampaign;
        }

        $campaign = (string) array_key_first($campaigns);
        abort_if($campaign === '' || ! isset($campaigns[$campaign]), 404, 'No active campaign configured.');

        return $campaign;
    }
}
