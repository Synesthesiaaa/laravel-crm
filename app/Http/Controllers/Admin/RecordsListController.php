<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CallHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordsListController extends Controller
{
    public function __construct(
        protected CallHistoryService $callHistoryService
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $history = $this->callHistoryService->getHistoryForCampaign(
            $campaign,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('agent'),
            25
        );
        return view('admin.records_list', [
            'history' => $history,
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }
}
