<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
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
        $salesFilter = $this->resolveSalesFilter($request);
        $kpis = $this->dashboardStats->getSalesKpisForCampaign(
            $campaign,
            $salesFilter['from'],
            $salesFilter['until'],
        );
        $dailyActivity = $this->dashboardStats->getLast24HourActivityTrend($campaign);
        $weeklyActivity = $this->dashboardStats->getWeeklyActivityTrend($campaign);
        $monthlyActivity = $this->dashboardStats->getMonthlyActivityTrend($campaign);
        $agentLeaderboard = $this->dashboardStats->getAgentLeaderboard($campaign);

        return view('dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'forms' => $forms,
            'kpis' => $kpis,
            'salesFilter' => $salesFilter,
            'dailyActivity' => $dailyActivity,
            'weeklyActivity' => $weeklyActivity,
            'monthlyActivity' => $monthlyActivity,
            'agentLeaderboard' => $agentLeaderboard,
        ]);
    }

    /**
     * @return array{date: string, start: string, end: string, from: Carbon, until: Carbon}
     */
    private function resolveSalesFilter(Request $request): array
    {
        $date = $request->query('sales_date');
        $start = $request->query('sales_start');
        $end = $request->query('sales_end');

        if ($date === null && $start === null && $end === null) {
            return $this->defaultSalesFilter();
        }

        if (! is_string($date) || ! is_string($start) || ! is_string($end)) {
            return $this->defaultSalesFilter();
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || ! preg_match('/^\d{2}:\d{2}$/', $start)
            || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return $this->defaultSalesFilter();
        }

        try {
            $from = Carbon::createFromFormat('!Y-m-d H:i', "{$date} {$start}", config('app.timezone'));
            $until = Carbon::createFromFormat('!Y-m-d H:i', "{$date} {$end}", config('app.timezone'));
        } catch (\Throwable) {
            return $this->defaultSalesFilter();
        }

        if ($from->format('Y-m-d') !== $date
            || $from->format('H:i') !== $start
            || $until->format('Y-m-d') !== $date
            || $until->format('H:i') !== $end
            || $until->lte($from)) {
            return $this->defaultSalesFilter();
        }

        return [
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'from' => $from,
            'until' => $until,
        ];
    }

    /**
     * @return array{date: string, start: string, end: string, from: Carbon, until: Carbon}
     */
    private function defaultSalesFilter(): array
    {
        $date = now(config('app.timezone'))->toDateString();
        $from = Carbon::createFromFormat('!Y-m-d H:i', "{$date} 06:00", config('app.timezone'));
        $until = Carbon::createFromFormat('!Y-m-d H:i', "{$date} 18:00", config('app.timezone'));

        return [
            'date' => $date,
            'start' => '06:00',
            'end' => '18:00',
            'from' => $from,
            'until' => $until,
        ];
    }
}
