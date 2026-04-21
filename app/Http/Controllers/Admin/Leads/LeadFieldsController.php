<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leads\StoreLeadFieldRequest;
use App\Http\Requests\Admin\Leads\UpdateLeadFieldRequest;
use App\Models\Campaign;
use App\Models\LeadListField;
use App\Services\Leads\LeadFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadFieldsController extends Controller
{
    public function __construct(
        protected LeadFieldService $fieldService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $currentCampaign = $request->session()->get('campaign', 'mbsales');
        $filterCampaign = $request->query('campaign', $currentCampaign);
        $campaigns = Campaign::active()->ordered()->get(['code', 'name']);

        $this->fieldService->ensureStandardFields($filterCampaign);
        $fields = $this->fieldService->getFields($filterCampaign);

        return view('admin.leads.fields.index', [
            'fields' => $fields,
            'campaigns' => $campaigns,
            'filterCampaign' => $filterCampaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreLeadFieldRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['field_key'] = $this->fieldService->normalizeKey($data['field_key']);
        $data['is_standard'] = false;
        $data['visible'] = $request->boolean('visible', true);
        $data['exportable'] = $request->boolean('exportable', true);
        $data['importable'] = $request->boolean('importable', true);

        LeadListField::create($data);

        return redirect()
            ->route('admin.leads.fields.index', ['campaign' => $data['campaign_code']])
            ->with('success', 'Field added.');
    }

    public function update(UpdateLeadFieldRequest $request, int $id): RedirectResponse
    {
        $field = LeadListField::findOrFail($id);

        $field->update([
            'field_label' => $request->input('field_label'),
            'field_type' => $request->input('field_type'),
            'field_options' => $request->input('field_options'),
            'visible' => $request->boolean('visible', true),
            'exportable' => $request->boolean('exportable', true),
            'importable' => $request->boolean('importable', true),
            'field_order' => (int) $request->input('field_order', $field->field_order),
        ]);

        return redirect()
            ->route('admin.leads.fields.index', ['campaign' => $field->campaign_code])
            ->with('success', 'Field updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $field = LeadListField::findOrFail((int) $request->input('id'));

        if ($field->is_standard) {
            return redirect()->back()->with('error', 'Standard fields cannot be deleted (toggle visibility instead).');
        }

        $campaign = $field->campaign_code;
        $field->delete();

        return redirect()
            ->route('admin.leads.fields.index', ['campaign' => $campaign])
            ->with('success', 'Field deleted.');
    }
}
