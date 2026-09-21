<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
    ) {}

    public function index(Request $request): View
    {
        $campaigns = $this->campaignService->getCampaigns();
        $selectedCampaign = trim((string) $request->query('campaign', ''));
        if ($selectedCampaign === '' || ! isset($campaigns[$selectedCampaign])) {
            $selectedCampaign = trim((string) $request->session()->get('campaign', ''));
        }
        if ($selectedCampaign === '' || ! isset($campaigns[$selectedCampaign])) {
            $selectedCampaign = (string) array_key_first($campaigns);
        }
        $scope = $selectedCampaign !== ''
            ? $this->scopeResolver->resolve($selectedCampaign)
            : null;

        return view('reports.index', [
            'campaign' => $selectedCampaign,
            'campaignName' => $campaigns[$selectedCampaign]['name']
                ?? $request->session()->get('campaign_name', 'CRM'),
            'reportCampaigns' => $campaigns,
            'vicidialCampaignCodes' => $scope?->historicalCampaignCodes() ?? [],
        ]);
    }
}
