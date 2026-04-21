<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignsController extends Controller
{
    public function __construct(protected CampaignService $campaignService) {}

    public function index(Request $request): View
    {
        $campaigns = Campaign::withCount('forms')->orderBy('display_order')->orderBy('id')->get();

        return view('admin.campaigns', [
            'campaigns' => $campaigns,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $v = $request->validated();
        Campaign::create([
            'code' => $v['code'],
            'name' => $v['name'],
            'description' => $v['description'] ?? '',
            'color' => $v['color'] ?? 'blue',
            'display_order' => (int) ($v['display_order'] ?? 0),
            'is_active' => true,
            'predictive_enabled' => $request->boolean('predictive_enabled', false),
            'predictive_delay_seconds' => (int) ($v['predictive_delay_seconds'] ?? 3),
            'predictive_max_attempts' => (int) ($v['predictive_max_attempts'] ?? 3),
        ]);
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign created.');
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $v = $request->validated();
        $campaign->update([
            'code' => $v['code'],
            'name' => $v['name'],
            'description' => $v['description'] ?? '',
            'color' => $v['color'] ?? 'blue',
            'display_order' => (int) ($v['display_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'predictive_enabled' => $request->boolean('predictive_enabled', false),
            'predictive_delay_seconds' => (int) ($v['predictive_delay_seconds'] ?? 3),
            'predictive_max_attempts' => (int) ($v['predictive_max_attempts'] ?? 3),
        ]);
        $this->campaignService->clearCampaignsCache();

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $c = Campaign::findOrFail((int) $request->input('id'));
        if ($c->forms()->exists()) {
            return redirect()->route('admin.campaigns.index')->with('error', 'Cannot delete campaign with existing forms.');
        }
        $c->update(['is_active' => false]);

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign deactivated.');
    }
}
