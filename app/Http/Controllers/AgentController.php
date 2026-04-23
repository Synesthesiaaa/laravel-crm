<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\DispositionRepositoryInterface;
use App\Models\AgentScreenField;
use App\Services\CampaignService;
use App\Services\TelephonyFeatureService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DispositionRepositoryInterface $dispositionRepository,
        protected TelephonyFeatureService $telephonyFeatureService,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');
        $campaignName = $request->session()->get('campaign_name', 'CRM');
        $dispositionCodes = $this->dispositionRepository->getForCampaign($campaign);

        $rawFields = AgentScreenField::forCampaign($campaign)->ordered()->get();

        $fields = $rawFields->map(function (AgentScreenField $f) {
            return (object) [
                'field_name' => $f->field_key,
                'field_type' => 'text',
                'label' => $f->field_label,
                'required' => false,
                'options_array' => [],
                'field_width' => $f->field_width ?? 'full',
            ];
        });

        // Allow Admin "Dial" button on leads pages to pre-fill the agent screen.
        $prefill = null;
        if ($request->query('prefill_phone')) {
            $prefill = [
                'phone' => preg_replace('/[^0-9+]/', '', (string) $request->query('prefill_phone')),
                'lead_id' => $request->query('prefill_lead_id'),
                'client_name' => $request->query('prefill_name'),
            ];
        }

        return view('agent.index', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'dispositionCodes' => $dispositionCodes,
            'fields' => $fields,
            'telephonyFeatures' => $this->telephonyFeatureService->getAll(),
            'prefill' => $prefill,
            'unifiedAgentSaveEnabled' => (bool) config('vicidial.unified_agent_save_enabled', false),
        ]);
    }
}
