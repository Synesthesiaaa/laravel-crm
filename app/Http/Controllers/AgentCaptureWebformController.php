<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\AgentCaptureWebformService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentCaptureWebformController extends Controller
{
    public function __construct(
        protected AgentCaptureWebformService $webformService,
    ) {}

    public function show(Request $request, string $campaign): View
    {
        $configuration = $this->webformService->configuration($campaign);
        if ($configuration) {
            $request->session()->put([
                'campaign' => $configuration['campaign']->code,
                'campaign_name' => $configuration['campaign']->name,
            ]);
        }
        $prefill = $configuration
            ? $this->webformService->prefill($configuration['fields'], $request)
            : ['lead_id' => null, 'phone_number' => null, 'fields' => []];
        $campaignName = $configuration['campaign']->name
            ?? Campaign::query()->where('code', $campaign)->value('name')
            ?? $campaign;

        return view('agent.capture_webform', [
            'configuration' => $configuration,
            'campaignCode' => $campaign,
            'campaignName' => $campaignName,
            'prefill' => $prefill,
        ]);
    }
}
