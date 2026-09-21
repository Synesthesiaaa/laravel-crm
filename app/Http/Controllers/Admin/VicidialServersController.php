<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVicidialServerRequest;
use App\Http\Requests\Admin\UpdateVicidialServerRequest;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialServer;
use App\Services\CampaignService;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\VicidialCampaignCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VicidialServersController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
        protected VicidialCampaignCatalogService $campaignCatalog,
    ) {}

    public function index(Request $request): View
    {
        $servers = VicidialServer::orderBy('campaign_code')->orderBy('priority')->get();
        $campaigns = $this->campaignService->getCampaigns();

        return view('admin.vicidial_servers', [
            'servers' => $servers,
            'campaigns' => $campaigns,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreVicidialServerRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $server = VicidialServer::create([
            'campaign_code' => $v['campaign_code'],
            'server_name' => $v['server_name'],
            'api_url' => $v['api_url'],
            'non_agent_api_url' => $v['non_agent_api_url'] ?? null,
            'db_host' => $v['db_host'],
            'db_username' => $v['db_username'],
            'db_password' => $v['db_password'] ?? '',
            'db_name' => $v['db_name'] ?? 'asterisk',
            'db_port' => (int) ($v['db_port'] ?? 3306),
            'api_user' => $v['api_user'] ?? null,
            'api_pass' => $v['api_pass'] ?? null,
            'source' => $v['source'] ?? 'crm_tracker',
            'is_active' => filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_default' => filter_var($v['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'priority' => (int) ($v['priority'] ?? 0),
        ]);
        $this->campaignService->clearCampaignsCache();
        $this->scopeResolver->clearForServer($server);
        $this->campaignCatalog->clear($server);

        return redirect()->route('admin.vicidial-servers.index')->with('success', 'Server added.');
    }

    public function update(UpdateVicidialServerRequest $request, VicidialServer $server): RedirectResponse
    {
        $v = $request->validated();
        $oldCampaign = $server->campaign_code;
        $server->update([
            'campaign_code' => $v['campaign_code'],
            'server_name' => $v['server_name'],
            'api_url' => $v['api_url'],
            'non_agent_api_url' => $v['non_agent_api_url'] ?? null,
            'db_host' => $v['db_host'],
            'db_username' => $v['db_username'],
            'db_name' => $v['db_name'] ?? 'asterisk',
            'db_port' => (int) ($v['db_port'] ?? 3306),
            'api_user' => $v['api_user'] ?? null,
            'source' => $v['source'] ?? 'crm_tracker',
            'is_active' => filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_default' => filter_var($v['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'priority' => (int) ($v['priority'] ?? 0),
        ]);
        if ($request->filled('db_password')) {
            $server->update(['db_password' => $v['db_password']]);
        }
        if ($request->filled('api_pass')) {
            $server->update(['api_pass' => $v['api_pass']]);
        }
        if ($oldCampaign !== $server->campaign_code) {
            CampaignVicidialMapping::query()
                ->where('vicidial_server_id', $server->getKey())
                ->update([
                    'is_enabled' => false,
                    'status' => CampaignVicidialMapping::STATUS_DISABLED,
                ]);
        }
        $this->campaignService->clearCampaignsCache();
        $this->scopeResolver->clear($oldCampaign);
        $this->scopeResolver->clearForServer($server);
        $this->campaignCatalog->clear($server);

        return redirect()->route('admin.vicidial-servers.index')->with('success', 'Server updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $server = VicidialServer::findOrFail((int) $request->input('id'));
        $campaign = $server->campaign_code;
        $server->delete();
        $this->campaignService->clearCampaignsCache();
        $this->scopeResolver->clear($campaign);
        $this->campaignCatalog->clear($server);

        return redirect()->route('admin.vicidial-servers.index')->with('success', 'Server deleted.');
    }
}
