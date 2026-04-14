<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFieldLogicRequest;
use App\Http\Requests\Admin\UpdateFieldLogicRequest;
use App\Models\FormField;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class FieldLogicController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function index(Request $request): Response
    {
        $campaign       = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms          = $campaignConfig['forms'] ?? [];
        $formType       = $request->query('form', array_key_first($forms) ?: '');
        if ($formType !== '' && !isset($forms[$formType])) {
            $formType = array_key_first($forms) ?: '';
        }
        $fields = FormField::where('campaign_code', $campaign)
            ->where('form_type', $formType)
            ->orderBy('field_order')
            ->orderBy('id')
            ->get();

        return $this->inertiaAdmin('admin.inline-field_logic', [
            'campaign'     => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms'        => $forms,
            'formType'     => $formType,
            'fields'       => $fields,
        ], 'Field Logic');
    }

    public function store(StoreFieldLogicRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $options   = $this->normalizeOptionsInput($request->input('options'));
        if (in_array($validated['field_type'], ['select', 'multiselect'], true) && $options === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['options' => 'Add at least one option (one per line) for select or multi-select fields.']);
        }
        if (! in_array($validated['field_type'], ['select', 'multiselect'], true)) {
            $options = null;
        }
        $maxOrder = FormField::where('campaign_code', $validated['campaign_code'])
            ->where('form_type', $validated['form_type'])
            ->max('field_order');
        FormField::create([
            'campaign_code' => $validated['campaign_code'],
            'form_type'     => $validated['form_type'],
            'field_name'    => $validated['field_name'],
            'field_label'   => $validated['field_label'],
            'field_type'    => $validated['field_type'],
            'is_required'   => $request->boolean('is_required'),
            'field_order'   => $validated['field_order'] ?? ($maxOrder ?? 0) + 1,
            'field_width'   => $validated['field_width'] ?? 'full',
            'options'       => $options,
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.field-logic.index', ['form' => $validated['form_type']])
            ->with('success', 'Field added.');
    }

    public function update(UpdateFieldLogicRequest $request, int $id): RedirectResponse
    {
        $field     = FormField::findOrFail($id);
        $validated = $request->validated();
        $newType   = $validated['field_type'] ?? $field->field_type;
        $options   = $this->normalizeOptionsInput($request->input('options'));
        if (in_array($newType, ['select', 'multiselect'], true) && $options === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['options' => 'Add at least one option (one per line) for select or multi-select fields.']);
        }
        if (! in_array($newType, ['select', 'multiselect'], true)) {
            $options = null;
        }
        $field->update([
            'field_label' => $validated['field_label'],
            'field_name'  => $validated['field_name'] ?? $field->field_name,
            'field_type'  => $newType,
            'is_required' => $request->boolean('is_required'),
            'field_order' => $validated['field_order'] ?? $field->field_order,
            'field_width' => $validated['field_width'] ?? $field->field_width,
            'options'     => $options,
        ]);
        $this->campaignService->clearCampaignsCache();
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
        $opts  = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $opts[] = $line;
            }
        }

        return $opts === [] ? null : json_encode(array_values($opts));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $id      = (int) $request->input('id');
        $field   = FormField::findOrFail($id);
        $formType = $field->form_type;
        $field->delete();
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.field-logic.index', ['form' => $formType])
            ->with('success', 'Field deleted.');
    }
}
