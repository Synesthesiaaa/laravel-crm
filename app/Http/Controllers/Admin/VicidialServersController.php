<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVicidialServerRequest;
use App\Http\Requests\Admin\UpdateVicidialServerRequest;
use App\Models\VicidialServer;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VicidialServersController extends Controller
{
    public function __construct(protected CampaignService $campaignService) {}

    public function index(Request $request): View
    {
        $servers   = VicidialServer::orderBy('campaign_code')->orderBy('priority')->get();
        $campaigns = $this->campaignService->getCampaigns();
        return view('admin.vicidial_servers', [
            'servers'      => $servers,
            'campaigns'    => $campaigns,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreVicidialServerRequest $request): RedirectResponse
    {
        $v = $request->validated();
        VicidialServer::create([
            'campaign_code' => $v['campaign_code'],
            'server_name'   => $v['server_name'],
            'api_url'       => $v['api_url'],
            'db_host'       => $v['db_host'],
            'db_username'   => $v['db_username'],
            'db_password'   => $v['db_password'] ?? '',
            'db_name'       => $v['db_name'] ?? 'asterisk',
            'db_port'       => (int) ($v['db_port'] ?? 3306),
            'is_active'     => true,
            'is_default'    => false,
            'priority'      => 0,
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.vicidial-servers.index')->with('success', 'Server added.');
    }

    public function update(UpdateVicidialServerRequest $request, VicidialServer $server): RedirectResponse
    {
        $v = $request->validated();
        $server->update([
            'campaign_code' => $v['campaign_code'],
            'server_name'   => $v['server_name'],
            'api_url'       => $v['api_url'],
            'db_host'       => $v['db_host'],
            'db_username'   => $v['db_username'],
            'db_name'       => $v['db_name'] ?? 'asterisk',
            'db_port'       => (int) ($v['db_port'] ?? 3306),
        ]);
        if ($request->filled('db_password')) {
            $server->update(['db_password' => $v['db_password']]);
        }
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.vicidial-servers.index')->with('success', 'Server updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        VicidialServer::findOrFail((int) $request->input('id'))->delete();
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.vicidial-servers.index')->with('success', 'Server deleted.');
    }
}
