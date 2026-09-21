<?php

namespace App\Http\Controllers\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardLayoutUpdateRequest;
use App\Services\AdminDashboardService;
use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRuleService;
use App\Services\DashboardStatsService;
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
        protected DashboardSalesRuleService $salesRuleService,
        protected DashboardStatsService $dashboardStats,
    ) {}

    public function index(Request $request): View
    {
        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaignCode($request, $campaigns);
        $campaignConfig = $campaigns[$campaign];
        $dashboardLayout = $this->layoutService->getForCampaign($campaign);

        return view('admin.dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'campaigns' => $campaigns,
            'stats' => $this->dashboardService->getFormStats($campaign),
            'userCount' => $this->dashboardService->getTotalUserCount(),
            'dashboardLayout' => $dashboardLayout,
            'dashboardSections' => DashboardLayoutService::sectionDefinitions(),
            'salesConfiguration' => $this->salesRuleService->resolveForCampaign($campaign, $dashboardLayout['sales'] ?? null),
            'salesEditorForms' => $this->salesRuleService->editorData($campaign),
            'user' => $request->user(),
        ]);
    }

    public function updateLayout(DashboardLayoutUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaignCode($request, $campaigns);
        $redirectWithCampaign = array_key_exists('campaign_code', $validated) || $request->query('campaign') !== null;
        $salesConfig = null;
        $replaceSalesConfig = false;

        if (array_key_exists('sales_mode', $validated)) {
            $replaceSalesConfig = true;
            if ($validated['sales_mode'] === DashboardSalesRuleService::MODE_CUSTOM) {
                $salesConfig = $this->salesRuleService->normalizeForPersistence([
                    'mode' => DashboardSalesRuleService::MODE_CUSTOM,
                    'forms' => $validated['sales_forms'] ?? [],
                ]);
            }
        }

        $this->layoutService->saveForCampaign(
            $campaign,
            $validated['section_order'],
            $validated['visible_sections'] ?? [],
            $salesConfig,
            $replaceSalesConfig,
            $validated['amounts'] ?? null,
        );
        $this->dashboardStats->invalidate($campaign);

        try {
            event(new DashboardLayoutUpdated($campaign));
        } catch (BroadcastException $exception) {
            report($exception);
        }

        $redirect = $redirectWithCampaign
            ? redirect()->route('admin.dashboard', ['campaign' => $campaign])
            : redirect()->route('admin.dashboard');

        return $redirect->with('success', 'Dashboard layout applied.');
    }

    /**
     * Resolve an admin dashboard campaign without changing the active campaign session.
     *
     * @param  array<string, array<string, mixed>>  $campaigns
     */
    private function resolveCampaignCode(Request $request, array $campaigns): string
    {
        $requested = trim((string) $request->query('campaign', $request->input('campaign_code', '')));
        if ($requested !== '' && isset($campaigns[$requested])) {
            return $requested;
        }

        $sessionCampaign = (string) $request->session()->get('campaign', '');
        if ($sessionCampaign !== '' && isset($campaigns[$sessionCampaign])) {
            return $sessionCampaign;
        }

        $campaign = (string) array_key_first($campaigns);
        abort_if($campaign === '' || ! isset($campaigns[$campaign]), 404, 'No active campaign configured.');

        return $campaign;
    }
}
