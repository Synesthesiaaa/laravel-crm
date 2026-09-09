<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRangeService;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DashboardStatsService $dashboardStats,
        protected DashboardLayoutService $dashboardLayoutService,
        protected DashboardSalesRangeService $dashboardSalesRangeService,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignName = $request->session()->get('campaign_name', 'Dashboard');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $salesFilter = $this->dashboardSalesRangeService->resolve($request);
        $kpis = $this->dashboardStats->getSalesKpisForCampaign(
            $campaign,
            $salesFilter['from'],
            $salesFilter['until'],
        );
        $dashboardSummary = $this->dashboardStats->getDashboardSummaryForCampaign($campaign);
        $dailyCampaignReport = $this->dashboardStats->getDailyCampaignReport(
            $campaign,
            now(config('app.timezone')),
        );
        $dashboardLayout = $this->dashboardLayoutService->getForCampaign($campaign);

        return view('dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'forms' => $forms,
            'kpis' => $kpis,
            'dashboardSummary' => $dashboardSummary,
            'dailyCampaignReport' => $dailyCampaignReport,
            'salesFilter' => $salesFilter,
            'agentLeaderboard' => $kpis['agent_leaderboard'] ?? [],
            'dashboardLayout' => $dashboardLayout,
            'salesMode' => data_get($dashboardLayout, 'sales.mode', 'legacy'),
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $campaign = (string) $request->session()->get('campaign', 'mbsales');

        return response()->json([
            'success' => true,
            'activity' => [
                'daily' => $this->dashboardStats->getLast24HourActivityTrend($campaign),
                'weekly' => $this->dashboardStats->getWeeklyActivityTrend($campaign),
                'monthly' => $this->dashboardStats->getMonthlyActivityTrend($campaign),
            ],
        ]);
    }
}
