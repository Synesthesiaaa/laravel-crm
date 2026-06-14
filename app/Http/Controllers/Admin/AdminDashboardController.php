<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected AdminDashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaign = $resolved['code'];
        $campaignConfig = $resolved['config'];

        return view('admin.dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'stats' => $this->dashboardService->getFormStats($campaign),
            'userCount' => $this->dashboardService->getTotalUserCount(),
            'user' => $request->user(),
        ]);
    }
}
