<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFieldLogicRequest;
use App\Http\Requests\Admin\UpdateFieldLogicRequest;
use App\Models\FormField;
use App\Services\CampaignService;
use App\Services\DashboardStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FieldLogicController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DashboardStatsService $dashboardStats,
    ) {}

    public function index(Request $request): View
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaign = $resolved['code'];
        $campaignConfig = $resolved['config'];
        $forms = $campaignConfig['forms'] ?? [];
        $formType = $forms === [] ? '' : (string) $request->query('form', array_key_first($forms) ?: '');
        if ($formType !== '' && ! isset($forms[$formType])) {
            $formType = array_key_first($forms) ?: '';
        }
        $fields = FormField::where('campaign_code', $campaign)
            ->where('form_type', $formType)
            ->orderBy('field_order')
            ->orderBy('id')
            ->get();

        return view('admin.field_logic', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'forms' => $forms,
            'formType' => $formType,
            'fields' => $fields,
        ]);
    }

    public function edit(Request $request, FormField $formField): View
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaign = $resolved['code'];
        $campaignConfig = $resolved['config'];
        $forms = $campaignConfig['forms'] ?? [];

        $siblingFields = FormField::query()
            ->where('campaign_code', $formField->campaign_code)
            ->where('form_type', $formField->form_type)
            ->where('id', '!=', $formField->id)
            ->orderBy('field_order')
            ->orderBy('id')
            ->get();

        $visibilityFieldOptions = $siblingFields
            ->mapWithKeys(fn (FormField $field) => [
                $field->field_name => $field->field_label !== ''
                    ? $field->field_label.' ('.$field->field_name.')'
                    : $field->field_name,
            ])
            ->all();

        $visibility = is_array($formField->visibility) ? $formField->visibility : [];
        $visibilityValuesText = '';
        if (! empty($visibility['values']) && is_array($visibility['values'])) {
            $visibilityValuesText = implode("\n", array_map(
                static fn ($value) => is_scalar($value) ? (string) $value : '',
                $visibility['values'],
            ));
        }

        return view('admin.field_logic_edit', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'forms' => $forms,
            'formType' => $formField->form_type,
            'field' => $formField,
            'visibilityFieldOptions' => $visibilityFieldOptions,
            'visibilityValuesText' => old('visibility.values.0', $visibilityValuesText),
        ]);
    }

    public function store(StoreFieldLogicRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $options = $this->normalizeOptionsInput($request->input('options'));
        if (in_array($validated['field_type'], ['select', 'multiselect'], true) && $options === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['options' => 'Add at least one option (one per line) for select or multi-select fields.']);
        }
        if (! in_array($validated['field_type'], ['select', 'multiselect'], true)) {
            $options = null;
        }
        $visibility = $this->normalizeVisibility($validated['visibility'] ?? null);
        $isSaleAmount = $request->boolean('is_sale_amount') && $validated['field_type'] === 'number';
        DB::transaction(function () use ($request, $validated, $options, $visibility, $isSaleAmount): void {
            $maxOrder = FormField::query()
                ->where('campaign_code', $validated['campaign_code'])
                ->where('form_type', $validated['form_type'])
                ->lockForUpdate()
                ->max('field_order');

            FormField::create([
                'campaign_code' => $validated['campaign_code'],
                'form_type' => $validated['form_type'],
                'field_name' => $validated['field_name'],
                'field_label' => $validated['field_label'],
                'field_type' => $validated['field_type'],
                'is_required' => $request->boolean('is_required'),
                'is_sale_amount' => $isSaleAmount,
                'field_order' => $validated['field_order'] ?? ($maxOrder ?? 0) + 1,
                'field_width' => $validated['field_width'] ?? 'full',
                'options' => $options,
                'visibility' => $visibility,
            ]);
        });
        $this->campaignService->clearCampaignsCache();
        $this->dashboardStats->invalidate($validated['campaign_code']);

        return redirect()->route('admin.field-logic.index', ['form' => $validated['form_type']])
            ->with('success', 'Field added.');
    }

    public function update(UpdateFieldLogicRequest $request, int $id): RedirectResponse
    {
        $field = FormField::findOrFail($id);
        $validated = $request->validated();
        $newType = $validated['field_type'] ?? $field->field_type;
        $options = $this->normalizeOptionsInput($request->input('options'));
        if (in_array($newType, ['select', 'multiselect'], true) && $options === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['options' => 'Add at least one option (one per line) for select or multi-select fields.']);
        }
        if (! in_array($newType, ['select', 'multiselect'], true)) {
            $options = null;
        }
        $visibility = $this->normalizeVisibility($validated['visibility'] ?? null);
        $isSaleAmount = $request->boolean('is_sale_amount') && $newType === 'number';
        $field->update([
            'field_label' => $validated['field_label'],
            'field_name' => $validated['field_name'] ?? $field->field_name,
            'field_type' => $newType,
            'is_required' => $request->boolean('is_required'),
            'is_sale_amount' => $isSaleAmount,
            'field_order' => $validated['field_order'] ?? $field->field_order,
            'field_width' => $validated['field_width'] ?? $field->field_width,
            'options' => $options,
            'visibility' => $visibility,
        ]);
        $this->campaignService->clearCampaignsCache();
        $this->dashboardStats->invalidate($field->campaign_code);

        return redirect()->route('admin.field-logic.index', ['form' => $field->form_type])
            ->with('success', 'Field updated.');
    }

    /** @return non-falsy-string|null JSON array of option strings */
    private function normalizeOptionsInput(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $opts = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $opts[] = $line;
            }
        }

        return $opts === [] ? null : json_encode(array_values($opts));
    }

    /**
     * @param  array<string, mixed>|null  $visibility
     * @return array<string, mixed>|null
     */
    private function normalizeVisibility(?array $visibility): ?array
    {
        $sourceField = trim((string) ($visibility['field'] ?? ''));
        $operator = trim((string) ($visibility['operator'] ?? ''));
        $rawValues = $visibility['values'] ?? [];

        if (! is_array($rawValues)) {
            $rawValues = [];
        }

        $values = [];
        foreach ($rawValues as $value) {
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $parts = preg_split('/\r\n|\r|\n|,/', $text) ?: [];
            foreach ($parts as $part) {
                $token = trim((string) $part);
                if ($token !== '') {
                    $values[] = $token;
                }
            }
        }

        $values = array_values(array_unique($values));

        if ($sourceField === '' || $operator === '' || $values === []) {
            return null;
        }

        return [
            'field' => $sourceField,
            'operator' => $operator,
            'values' => $values,
        ];
    }

    public function destroy(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $field = FormField::findOrFail($id);
        $formType = $field->form_type;
        $campaignCode = $field->campaign_code;
        $field->delete();
        $this->campaignService->clearCampaignsCache();
        $this->dashboardStats->invalidate($campaignCode);

        return redirect()->route('admin.field-logic.index', ['form' => $formType])
            ->with('success', 'Field deleted.');
    }
}
