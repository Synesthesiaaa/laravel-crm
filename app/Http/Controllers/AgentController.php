<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\DispositionRepositoryInterface;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DispositionRepositoryInterface $dispositionRepository
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignName = $request->session()->get('campaign_name', 'CRM');
        $dispositionCodes = $this->dispositionRepository->getForCampaign($campaign);

        return view('agent.index', [
            'campaign' => $campaign,
            'campaignName' => $campaignName,
            'user' => $request->user(),
            'dispositionCodes' => $dispositionCodes,
        ]);
    }
}
