<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFieldLogicRequest;
use App\Http\Requests\Admin\UpdateFieldLogicRequest;
use App\Models\FormField;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldLogicController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function index(Request $request): View
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
        return view('admin.field_logic', [
            'campaign'     => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms'        => $forms,
            'formType'     => $formType,
            'fields'       => $fields,
        ]);
    }

    public function store(StoreFieldLogicRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $maxOrder  = FormField::where('campaign_code', $validated['campaign_code'])
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
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.field-logic.index', ['form' => $validated['form_type']])
            ->with('success', 'Field added.');
    }

    public function update(UpdateFieldLogicRequest $request, int $id): RedirectResponse
    {
        $field     = FormField::findOrFail($id);
        $validated = $request->validated();
        $field->update([
            'field_label' => $validated['field_label'],
            'field_name'  => $validated['field_name'] ?? $field->field_name,
            'field_type'  => $validated['field_type'] ?? $field->field_type,
            'is_required' => $request->boolean('is_required'),
            'field_order' => $validated['field_order'] ?? $field->field_order,
            'field_width' => $validated['field_width'] ?? $field->field_width,
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.field-logic.index', ['form' => $field->form_type])
            ->with('success', 'Field updated.');
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
