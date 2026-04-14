<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected AdminDashboardService $dashboardService
    ) {}

    public function index(Request $request): Response
    {
        $campaign       = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['name' => $campaign, 'forms' => []];

        return $this->inertiaAdmin('admin.inline-dashboard', [
            'campaign'     => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'stats'        => $this->dashboardService->getFormStats($campaign),
            'userCount'    => $this->dashboardService->getTotalUserCount(),
            'user'         => $request->user(),
        ], 'Admin Dashboard');
    }
}
