<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $formType = $request->query('form', array_key_first($forms) ?: '');
        if ($formType !== '' && !isset($forms[$formType])) {
            $formType = array_key_first($forms) ?: '';
        }
        $fields = FormField::where('campaign_code', $campaign)
            ->where('form_type', $formType)
            ->orderBy('field_order')
            ->orderBy('id')
            ->get();
        return view('admin.field_logic', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms' => $forms,
            'formType' => $formType,
            'fields' => $fields,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_code' => 'required|string|max:50',
            'form_type' => 'required|string|max:50',
            'field_name' => 'required|string|max:80',
            'field_label' => 'required|string|max:255',
            'field_type' => 'required|in:text,textarea,number,date,select',
            'is_required' => 'nullable|boolean',
            'field_order' => 'nullable|integer',
            'field_width' => 'nullable|in:full,half,third',
        ]);
        $maxOrder = FormField::where('campaign_code', $validated['campaign_code'])
            ->where('form_type', $validated['form_type'])
            ->max('field_order');
        FormField::create([
            'campaign_code' => $validated['campaign_code'],
            'form_type' => $validated['form_type'],
            'field_name' => $validated['field_name'],
            'field_label' => $validated['field_label'],
            'field_type' => $validated['field_type'],
            'is_required' => $request->boolean('is_required'),
            'field_order' => $validated['field_order'] ?? $maxOrder + 1,
            'field_width' => $validated['field_width'] ?? 'full',
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.field-logic.index', ['form' => $validated['form_type']])
            ->with('success', 'Field added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $field = FormField::findOrFail($id);
        $validated = $request->validate([
            'field_label' => 'required|string|max:255',
            'field_name' => 'sometimes|string|max:80',
            'is_required' => 'nullable|boolean',
            'field_order' => 'nullable|integer',
            'field_width' => 'nullable|in:full,half,third',
        ]);
        $field->update([
            'field_label' => $validated['field_label'],
            'field_name' => $validated['field_name'] ?? $field->field_name,
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
        $id = (int) $request->input('id');
        $field = FormField::findOrFail($id);
        $formType = $field->form_type;
        $field->delete();
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.field-logic.index', ['form' => $formType])
            ->with('success', 'Field deleted.');
    }
}
