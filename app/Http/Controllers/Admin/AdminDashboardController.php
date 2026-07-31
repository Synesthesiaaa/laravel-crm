<?php

namespace App\Http\Controllers\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardLayoutUpdateRequest;
use App\Services\AdminDashboardService;
use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected AdminDashboardService $dashboardService,
        protected DashboardLayoutService $layoutService,
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
            'dashboardLayout' => $this->layoutService->getForCampaign($campaign),
            'dashboardSections' => DashboardLayoutService::sectionDefinitions(),
            'user' => $request->user(),
        ]);
    }

    public function updateLayout(DashboardLayoutUpdateRequest $request): RedirectResponse
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $validated = $request->validated();

        $this->layoutService->saveForCampaign(
            $resolved['code'],
            $validated['section_order'],
            $validated['visible_sections'] ?? [],
        );

        try {
            event(new DashboardLayoutUpdated($resolved['code']));
        } catch (BroadcastException $exception) {
            report($exception);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Dashboard layout applied.');
    }
}
