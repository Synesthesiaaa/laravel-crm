<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgentScreenFieldRequest;
use App\Http\Requests\Admin\UpdateAgentScreenFieldRequest;
use App\Models\AgentScreenField;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentScreenController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function index(Request $request): View
    {
        $campaigns = $this->campaignService->getCampaigns();
        $selectedCampaign = $request->query('campaign', array_key_first($campaigns) ?? '');
        if ($selectedCampaign !== '' && ! isset($campaigns[$selectedCampaign])) {
            $selectedCampaign = array_key_first($campaigns) ?? '';
        }
        $fields = AgentScreenField::where('campaign_code', $selectedCampaign)
            ->orderBy('field_order')
            ->orderBy('id')
            ->get();

        return view('admin.agent_screen', [
            'campaigns' => $campaigns,
            'fields' => $fields,
            'selectedCampaign' => $selectedCampaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreAgentScreenFieldRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $exists = AgentScreenField::where('campaign_code', $validated['campaign_code'])
            ->where('field_key', $validated['field_key'])
            ->exists();
        if ($exists) {
            return back()->with('error', 'Field key already exists for this campaign.');
        }
        $maxOrder = AgentScreenField::where('campaign_code', $validated['campaign_code'])->max('field_order');
        AgentScreenField::create([
            'campaign_code' => $validated['campaign_code'],
            'field_key' => $validated['field_key'],
            'field_label' => $validated['field_label'],
            'field_order' => ($maxOrder ?? 0) + 1,
            'field_width' => $validated['field_width'] ?? 'full',
        ]);
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.agent-screen.index', ['campaign' => $validated['campaign_code']])
            ->with('success', 'Field added.');
    }

    public function update(UpdateAgentScreenFieldRequest $request, AgentScreenField $field): RedirectResponse
    {
        $validated = $request->validated();
        $field->update([
            'field_label' => $validated['field_label'],
            'field_width' => $validated['field_width'] ?? 'full',
        ]);
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.agent-screen.index', ['campaign' => $field->campaign_code])
            ->with('success', 'Field updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $field = AgentScreenField::findOrFail($id);
        $campaign = $field->campaign_code;
        $field->delete();
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.agent-screen.index', ['campaign' => $campaign])
            ->with('success', 'Field removed.');
    }
}
