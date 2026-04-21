<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DashboardStatsService $dashboardStats,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignName = $request->session()->get('campaign_name', 'Dashboard');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $activityTrend = $this->dashboardStats->getActivityTrend($campaign, 14);
        $topAgents = $this->dashboardStats->getTopAgents($campaign, 10);

        return view('dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'forms' => $forms,
            'activityTrend' => $activityTrend,
            'topAgents' => $topAgents,
        ]);
    }
}
