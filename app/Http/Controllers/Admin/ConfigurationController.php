<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Models\Campaign;
use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Services\AgentDataMasterAccessService;
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
    private const TABS = ['general', 'branding', 'disposition', 'telephony', 'data-master', 'diagnostics', 'retention'];

    public function __construct(
        protected CampaignService $campaignService,
        protected BrandingService $brandingService,
        protected TelephonyFeatureService $telephonyFeatureService,
        protected DataRetentionService $dataRetentionService,
        protected ReportDispositionSettingsService $reportDispositionSettingsService,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'general');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'general';
        }

        $data = ['tab' => $tab];

        $data += match ($tab) {
            'data-master' => $this->dataMasterTabData($request),
            'branding' => [
                'brandingSettings' => $this->brandingService->resolve(),
            ],
            'disposition' => [
                'reportDispositionSettings' => $this->reportDispositionSettingsService->resolve(),
            ],
            'telephony' => [
                'telephonyFeatures' => $this->telephonyFeatureService->getAll(),
            ],
            'retention' => $this->retentionTabData($request),
            'diagnostics' => [],
            default => [
                'campaigns' => $this->campaignService->getCampaigns(),
            ],
        };

        return view('admin.configuration', $data);
    }

    private function dataMasterTabData(Request $request): array
    {
        $campaigns = Campaign::query()->active()->ordered()->get();
        $requested = $request->query('data_master_campaign', $request->session()->get('campaign'));
        $selected = $campaigns->firstWhere('code', is_string($requested) ? $requested : '') ?? $campaigns->first();

        return [
            'dataMasterCampaigns' => $campaigns,
            'selectedDataMasterCampaign' => $selected,
            'dataMasterForms' => $selected ? $selected->forms()->active()->orderBy('display_order')->orderBy('id')->get() : collect(),
            'dataMasterAccess' => app(AgentDataMasterAccessService::class)->settings($selected?->code ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function retentionTabData(Request $request): array
    {
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
        $selectedRetentionForm = $retentionForms->firstWhere('id', $selectedRetentionFormId);
        if ($selectedRetentionForm instanceof Form) {
            $selectedRetentionForm->setRelation(
                'formFields',
                $this->dataRetentionService->eligibleFields($selectedRetentionForm),
            );
        }

        return [
            'retentionForms' => $retentionForms,
            'retentionPolicies' => DataRetentionPolicy::query()
                ->with('form.campaign')
                ->latest('id')
                ->get(),
            'selectedRetentionFormId' => $selectedRetentionFormId,
        ];
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
