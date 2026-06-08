<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\DispositionRepositoryInterface;
use App\Models\AgentScreenField;
use App\Services\CampaignService;
use App\Services\Telephony\TelephonyCampaignResolver;
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
        $campaign = TelephonyCampaignResolver::forRequest($request);
        $campaignName = $request->session()->get(
            'vicidial_campaign_name',
            $request->session()->get('campaign_name', 'CRM'),
        );
        $dispositionCodes = $this->dispositionRepository->getForCampaign($campaign);

        $rawFields = AgentScreenField::forCampaign($campaign)->ordered()->get();

        $fields = $rawFields->map(function (AgentScreenField $f) {
            return (object) [
                'field_name' => $f->field_key,
                'field_type' => $f->field_type ?: 'text',
                'label' => $f->field_label,
                'required' => (bool) $f->is_required,
                'options_array' => is_array($f->options) ? $f->options : [],
                'placeholder' => $f->placeholder,
                'visibility' => is_array($f->visibility) ? $f->visibility : null,
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
