<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAgentDataMasterAccessRequest;
use App\Services\AgentDataMasterAccessService;
use Illuminate\Http\RedirectResponse;

class AgentDataMasterAccessController extends Controller
{
    public function update(UpdateAgentDataMasterAccessRequest $request, string $campaign, AgentDataMasterAccessService $access): RedirectResponse
    {
        $access->update($campaign, $request->validated());

        return redirect()->route('admin.configuration', ['tab' => 'data-master', 'data_master_campaign' => $campaign])
            ->with('status', 'Agent Data Master permissions updated.');
    }
}
