<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Services\BrandingService;
use App\Services\CampaignService;
use App\Services\DataRetentionService;
use App\Services\Telephony\ReportDispositionSettingsService;
use App\Services\TelephonyFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected BrandingService $brandingService,
        protected TelephonyFeatureService $telephonyFeatureService,
        protected DataRetentionService $dataRetentionService,
        protected ReportDispositionSettingsService $reportDispositionSettingsService,
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
        $retentionForms->each(function (Form $form): void {
            $form->setRelation('formFields', $this->dataRetentionService->eligibleFields($form));
        });
        $selectedRetentionFormId = (int) $request->query('retention_form', 0);
        if (! $retentionForms->contains('id', $selectedRetentionFormId)) {
            $selectedRetentionFormId = (int) ($retentionForms->first()?->id ?? 0);
        }

        return view('admin.configuration', [
            'tab' => $tab,
            'brandingSettings' => $this->brandingService->resolve(),
            'campaigns' => $campaigns,
            'telephonyFeatures' => $this->telephonyFeatureService->getAll(),
            'reportDispositionSettings' => $this->reportDispositionSettingsService->resolve(),
            'retentionForms' => $retentionForms,
            'retentionPolicies' => DataRetentionPolicy::query()
                ->with('form.campaign')
                ->latest('id')
                ->get(),
            'selectedRetentionFormId' => $selectedRetentionFormId,
        ]);
    }

    public function updateBranding(UpdateBrandingRequest $request): RedirectResponse
    {
        $this->brandingService->update($request->validated());

        return redirect()
            ->route('admin.configuration', ['tab' => 'branding'])
            ->with('status', 'Branding settings updated successfully.');
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

    public function updateReportDispositions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hide_system_dispositions' => ['nullable', 'boolean'],
            'system_disposition_codes' => ['nullable', 'string', 'max:1000'],
        ]);
        $hideSystemDispositions = $request->boolean('hide_system_dispositions');
        $codes = (string) ($validated['system_disposition_codes'] ?? '');

        if ($hideSystemDispositions && $this->reportDispositionSettingsService->normalizeCodes($codes) === []) {
            throw ValidationException::withMessages([
                'system_disposition_codes' => 'Add at least one VICIdial system disposition code before enabling this option.',
            ]);
        }

        $this->reportDispositionSettingsService->update($hideSystemDispositions, $codes);

        return redirect()
            ->route('admin.configuration', ['tab' => 'disposition'])
            ->with('status', 'Report disposition settings updated.');
    }
}
