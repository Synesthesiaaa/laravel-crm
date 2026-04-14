<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DashboardStatsService $dashboardStats
    ) {}

    public function index(Request $request): Response
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $activityTrend = $this->dashboardStats->getActivityTrend($campaign, 14);
        $topAgents = $this->dashboardStats->getTopAgents($campaign, 10);

        return Inertia::render('Dashboard', [
            'forms' => $forms,
            'activityTrend' => $activityTrend,
            'topAgents' => $topAgents,
        ]);
    }
}
