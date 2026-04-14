<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\DispositionRepositoryInterface;
use App\Models\AgentScreenField;
use App\Services\CampaignService;
use App\Services\TelephonyFeatureService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DispositionRepositoryInterface $dispositionRepository,
        protected TelephonyFeatureService $telephonyFeatureService
    ) {}

    public function index(Request $request): Response
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
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

        $telephonyFeatures = $this->telephonyFeatureService->getAll();

        $agentMarkup = view('agent.alpine-markup', [
            'fields' => $fields,
            'dispositionCodes' => $dispositionCodes,
            'telephonyFeatures' => $telephonyFeatures,
        ])->render();

        return Inertia::render('Agent/Index', [
            'agentMarkup' => $agentMarkup,
            'telephonyFeatures' => $telephonyFeatures,
        ]);
    }
}
