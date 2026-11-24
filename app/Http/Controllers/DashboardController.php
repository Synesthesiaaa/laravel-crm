<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRangeService;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        $activityTrends = [];
        $activityPeriods = [];
        $activityQuery = [];
        if ($forms !== [] && data_get($dashboardLayout, 'sections.activity.visible', true)) {
            $today = now()->startOfDay();
            $currentWeek = $today->copy()->startOfWeek(CarbonInterface::MONDAY);
            $currentMonth = $today->copy()->startOfMonth();
            $week = $this->activityDate($request->query('activity_week'), 'Y-m-d', $today)->startOfWeek(CarbonInterface::MONDAY);
            $month = $this->activityDate($request->query('activity_month'), 'Y-m', $today)->startOfMonth();
            $weekEnd = $week->copy()->addDays(6);
            $activityPeriods = [
                'weekly' => [
                    'label' => $week->format($week->year === $weekEnd->year ? 'M j' : 'M j, Y').' – '.$weekEnd->format('M j, Y'),
                    'previous' => $week->copy()->subWeek()->toDateString(),
                    'next' => $week->lt($currentWeek) ? $week->copy()->addWeek()->toDateString() : null,
                ],
                'monthly' => [
                    'label' => $month->format('F Y'),
                    'previous' => $month->copy()->subMonth()->format('Y-m'),
                    'next' => $month->lt($currentMonth) ? $month->copy()->addMonth()->format('Y-m') : null,
                ],
            ];
            $activityQuery = array_filter([
                'activity_week' => $week->lt($currentWeek) ? $week->toDateString() : null,
                'activity_month' => $month->lt($currentMonth) ? $month->format('Y-m') : null,
            ]);
            $activityTrends = array_map(static fn (array $trend): array => [
                'labels' => $trend['labels'],
                'forms' => $trend['forms'],
            ], [
                'daily' => $this->dashboardStats->getLast24HourActivityTrend($campaign),
                'weekly' => $this->dashboardStats->getWeeklyActivityTrend($campaign, $week),
                'monthly' => $this->dashboardStats->getMonthlyActivityTrend($campaign, $month),
            ]);
        }

        return view('dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'forms' => $forms,
            'kpis' => $kpis,
            'dashboardSummary' => $dashboardSummary,
            'dailyCampaignReport' => $dailyCampaignReport,
            'salesFilter' => $salesFilter,
            'activityTrends' => $activityTrends,
            'activityPeriods' => $activityPeriods,
            'activityQuery' => $activityQuery,
            'agentLeaderboard' => $kpis['agent_leaderboard'] ?? [],
            'dashboardLayout' => $dashboardLayout,
            'salesMode' => data_get($dashboardLayout, 'sales.mode', 'legacy'),
        ]);
    }

    private function activityDate(mixed $value, string $format, Carbon $today): Carbon
    {
        if (! is_string($value) || strlen($value) !== strlen($today->format($format))
            || Validator::make(['period' => $value], ['period' => 'required|date_format:'.$format])->fails()) {
            return $today->copy();
        }

        $date = Carbon::createFromFormat('!'.$format, $value, $today->getTimezone());

        return $date->lte($today) ? $date : $today->copy();
    }
}
