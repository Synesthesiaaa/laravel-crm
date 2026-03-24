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
        protected TelephonyFeatureService $telephonyFeatureService
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
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

        return view('agent.index', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'dispositionCodes' => $dispositionCodes,
            'fields' => $fields,
            'telephonyFeatures' => $this->telephonyFeatureService->getAll(),
        ]);
    }
}
