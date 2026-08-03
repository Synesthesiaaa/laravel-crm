<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRetentionPolicy;
use App\Models\Form;
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
        $retentionForms = Form::query()
            ->where('is_active', true)
            ->with('retentionPolicy')
            ->orderBy('campaign_code')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $selectedRetentionFormId = (int) $request->query('retention_form', 0);
        if (! $retentionForms->contains('id', $selectedRetentionFormId)) {
            $selectedRetentionFormId = (int) ($retentionForms->first()?->id ?? 0);
        }

        return view('admin.configuration', [
            'tab' => $tab,
            'campaigns' => $campaigns,
            'telephonyFeatures' => $this->telephonyFeatureService->getAll(),
            'retentionForms' => $retentionForms,
            'retentionPolicies' => DataRetentionPolicy::query()
                ->with('form.campaign')
                ->latest('id')
                ->get(),
            'selectedRetentionFormId' => $selectedRetentionFormId,
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
