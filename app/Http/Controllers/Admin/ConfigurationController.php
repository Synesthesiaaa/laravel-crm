<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Services\TelephonyFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected TelephonyFeatureService $telephonyFeatureService,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'general');
        $campaigns = $this->campaignService->getCampaigns();

        return view('admin.configuration', [
            'tab' => $tab,
            'campaigns' => $campaigns,
            'telephonyFeatures' => $this->telephonyFeatureService->getAll(),
        ]);
    }

    public function updateTelephonyFeatures(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'features' => ['array'],
            'features.*' => ['nullable', 'in:1,on,true,yes'],
        ]);

        $this->telephonyFeatureService->updateMany($validated['features'] ?? []);

        return redirect()
            ->route('admin.configuration', ['tab' => 'telephony'])
            ->with('status', 'Telephony feature access updated.');
    }
}
