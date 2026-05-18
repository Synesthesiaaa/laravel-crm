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
            'viciFields' => config('vicidial_fields', []),
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
        $fieldType = (string) ($validated['field_type'] ?? 'text');
        $fieldOrder = isset($validated['field_order']) ? (int) $validated['field_order'] : (($maxOrder ?? 0) + 1);

        AgentScreenField::create([
            'campaign_code' => $validated['campaign_code'],
            'field_key' => $validated['field_key'],
            'vici_field' => $this->normalizeNullable($validated['vici_field'] ?? null),
            'field_label' => $validated['field_label'],
            'field_type' => $fieldType,
            'direction' => (string) ($validated['direction'] ?? 'get'),
            'options' => $fieldType === 'select' ? $this->parseOptions($validated['options'] ?? null) : [],
            'placeholder' => $this->normalizeNullable($validated['placeholder'] ?? null),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'field_order' => $fieldOrder,
            'field_width' => $validated['field_width'] ?? 'full',
        ]);
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.agent-screen.index', ['campaign' => $validated['campaign_code']])
            ->with('success', 'Field added.');
    }

    public function update(UpdateAgentScreenFieldRequest $request, AgentScreenField $field): RedirectResponse
    {
        $validated = $request->validated();
        $fieldType = (string) ($validated['field_type'] ?? 'text');

        $field->update([
            'field_key' => $validated['field_key'],
            'field_label' => $validated['field_label'],
            'vici_field' => $this->normalizeNullable($validated['vici_field'] ?? null),
            'field_type' => $fieldType,
            'direction' => (string) ($validated['direction'] ?? 'get'),
            'options' => $fieldType === 'select' ? $this->parseOptions($validated['options'] ?? null) : [],
            'placeholder' => $this->normalizeNullable($validated['placeholder'] ?? null),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'field_order' => isset($validated['field_order']) ? (int) $validated['field_order'] : $field->field_order,
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

    /**
     * @return array<int, string>
     */
    private function parseOptions(?string $options): array
    {
        if (! $options) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($line) => trim((string) $line),
            preg_split('/\r\n|\r|\n/', $options) ?: []
        ), static fn ($line) => $line !== ''));
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
