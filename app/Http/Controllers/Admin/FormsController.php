<?php

namespace App\Http\Controllers\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFormRequest;
use App\Http\Requests\Admin\UpdateFormRequest;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRuleService;
use App\Services\DashboardStatsService;
use App\Services\FormStorageSchemaService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class FormsController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DashboardLayoutService $layoutService,
        protected DashboardSalesRuleService $salesRuleService,
        protected DashboardStatsService $dashboardStats,
        protected FormStorageSchemaService $formStorageSchema,
    ) {}

    public function index(Request $request): View
    {
        $campaigns = Campaign::where('is_active', true)->orderBy('display_order')->get();
        $selectedCampaign = $request->query('campaign', $campaigns->first()?->code ?? '');
        $forms = Form::with('campaign')
            ->where('campaign_code', $selectedCampaign)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $dashboardLayout = $this->layoutService->getForCampaign($selectedCampaign);
        $storedSales = is_array($dashboardLayout['sales'] ?? null) ? $dashboardLayout['sales'] : [];
        $salesRulesByForm = collect($storedSales['forms'] ?? [])
            ->filter(fn ($rule) => is_array($rule) && trim((string) ($rule['form_code'] ?? '')) !== '')
            ->keyBy(fn ($rule) => (string) $rule['form_code'])
            ->all();
        $salesEditorForms = collect($this->salesRuleService->editorData($selectedCampaign))
            ->keyBy('code')
            ->all();

        return view('admin.forms', [
            'campaigns' => $campaigns,
            'forms' => $forms,
            'selectedCampaign' => $selectedCampaign,
            'campaignName' => $campaigns->firstWhere('code', $selectedCampaign)?->name
                ?? $request->session()->get('campaign_name', 'CRM'),
            'salesMode' => ($storedSales['mode'] ?? null) === DashboardSalesRuleService::MODE_CUSTOM
                ? DashboardSalesRuleService::MODE_CUSTOM
                : DashboardSalesRuleService::MODE_LEGACY,
            'salesRulesByForm' => $salesRulesByForm,
            'salesEditorForms' => $salesEditorForms,
            'salesConfiguration' => $this->salesRuleService->resolveForCampaign(
                $selectedCampaign,
                $dashboardLayout['sales'] ?? null,
            ),
        ]);
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $exists = Form::where('campaign_code', $validated['campaign_code'])->where('form_code', $validated['form_code'])->exists();
        if ($exists) {
            return back()->with('error', 'Form code already exists for this campaign.');
        }
        $form = Form::create([
            'campaign_code' => $validated['campaign_code'],
            'form_code' => $validated['form_code'],
            'name' => $validated['name'],
            'table_name' => $validated['table_name'],
            'color' => $validated['color'] ?? 'blue',
            'icon' => $validated['icon'] ?? 'form',
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->campaignService->clearCampaignsCache();
        $storageFailure = $this->provisionFormStorage($form);

        $redirect = redirect()->route('admin.forms.index', ['campaign' => $validated['campaign_code']])
            ->with('success', 'Form created.');

        return $storageFailure === null ? $redirect : $redirect->with('error', $storageFailure);
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
        $validated = $request->validated();
        $exists = Form::where('campaign_code', $validated['campaign_code'])
            ->where('form_code', $validated['form_code'])
            ->where('id', '!=', $form->id)
            ->exists();
        if ($exists) {
            return back()->with('error', 'Form code already exists for this campaign.');
        }

        $originalFormCode = (string) $form->form_code;
        $hasSalesRule = array_key_exists('sales_rule', $validated);
        $salesRule = is_array($validated['sales_rule'] ?? null) ? $validated['sales_rule'] : [];

        DB::transaction(function () use ($form, $validated, $request, $originalFormCode, $hasSalesRule, $salesRule): void {
            $form->update([
                'campaign_code' => $validated['campaign_code'],
                'form_code' => $validated['form_code'],
                'name' => $validated['name'],
                'table_name' => $validated['table_name'],
                'color' => $validated['color'] ?? 'blue',
                'icon' => $validated['icon'] ?? 'form',
                'display_order' => $validated['display_order'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($hasSalesRule) {
                $this->saveSalesAttribution($form, $originalFormCode, $salesRule);
            }
        });

        $this->campaignService->clearCampaignsCache();
        $this->dashboardStats->invalidate((string) $form->campaign_code);
        $storageFailure = $this->provisionFormStorage($form);

        try {
            event(new DashboardLayoutUpdated((string) $form->campaign_code));
        } catch (BroadcastException $exception) {
            report($exception);
        }

        $redirect = redirect()->route('admin.forms.index', ['campaign' => $form->campaign_code])
            ->with('success', 'Form updated.');

        return $storageFailure === null ? $redirect : $redirect->with('error', $storageFailure);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $form = Form::findOrFail((int) $request->input('id'));
        $form->update(['is_active' => false]);
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.forms.index', ['campaign' => $form->campaign_code])->with('success', 'Form deactivated.');
    }

    private function provisionFormStorage(Form $form): ?string
    {
        try {
            $fields = FormField::query()
                ->where('campaign_code', $form->campaign_code)
                ->where('form_type', $form->form_code)
                ->get();
            $this->formStorageSchema->provision((string) $form->table_name, $fields);

            return null;
        } catch (Throwable $exception) {
            $reference = (string) Str::uuid();
            Log::error('Form storage provisioning failed after form metadata change.', [
                'reference' => $reference,
                'form_id' => $form->id,
                'exception_class' => $exception::class,
            ]);

            return 'Form settings were saved, but storage provisioning needs attention. Run forms:provision-storage. Reference: '.$reference;
        }
    }

    /**
     * Update only this form's custom sales rule while preserving the rest of
     * the campaign's dashboard layout and other form attribution rules.
     *
     * @param  array<string, mixed>  $salesRule
     */
    private function saveSalesAttribution(Form $form, string $originalFormCode, array $salesRule): void
    {
        $campaignCode = (string) $form->campaign_code;
        $newFormCode = (string) $form->form_code;
        $layout = $this->layoutService->getForCampaign($campaignCode);
        $storedSales = is_array($layout['sales'] ?? null) ? $layout['sales'] : [];
        $rules = ($storedSales['mode'] ?? null) === DashboardSalesRuleService::MODE_CUSTOM
            ? (array) ($storedSales['forms'] ?? [])
            : [];

        $rules = array_values(array_filter(
            $rules,
            static function (mixed $rule) use ($originalFormCode, $newFormCode): bool {
                if (! is_array($rule)) {
                    return false;
                }

                $formCode = (string) ($rule['form_code'] ?? '');

                return $formCode !== $originalFormCode && $formCode !== $newFormCode;
            },
        ));

        if ((bool) ($salesRule['enabled'] ?? false)) {
            $rules[] = [
                'form_code' => $newFormCode,
                'amount_field' => $salesRule['amount_field'] ?? null,
                'trigger' => $salesRule['trigger'] ?? DashboardSalesRuleService::TRIGGER_FORM,
                'conditions' => is_array($salesRule['conditions'] ?? null) ? $salesRule['conditions'] : [],
            ];
        }

        $salesConfig = $rules === []
            ? null
            : $this->salesRuleService->normalizeForPersistence([
                'mode' => DashboardSalesRuleService::MODE_CUSTOM,
                'forms' => $rules,
            ]);

        $this->layoutService->saveSalesForCampaign($campaignCode, $salesConfig);
    }
}
