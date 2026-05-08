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
        $kpis = $this->dashboardStats->getKpisForCampaign($campaign);
        $dailyDays = (int) config('dashboard.daily_activity_days', 7);
        $dailyActivity = $this->dashboardStats->getActivityTrend($campaign, $dailyDays);
        $weeklyActivity = $this->dashboardStats->getWeeklyActivityTrend($campaign);
        $monthlyActivity = $this->dashboardStats->getMonthlyActivityTrend($campaign);
        $agentLeaderboard = $this->dashboardStats->getAgentLeaderboard($campaign);

        return view('dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'forms' => $forms,
            'kpis' => $kpis,
            'dailyActivity' => $dailyActivity,
            'weeklyActivity' => $weeklyActivity,
            'monthlyActivity' => $monthlyActivity,
            'agentLeaderboard' => $agentLeaderboard,
            'dailyActivityDays' => $dailyDays,
        ]);
    }
}
