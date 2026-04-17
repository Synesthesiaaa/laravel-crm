<?php

namespace App\Http\Controllers;

use App\Services\CallHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordsController extends Controller
{
    public function __construct(
        protected CallHistoryService $callHistoryService,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $history = $this->callHistoryService->getHistoryForCampaign(
            $campaign,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('agent'),
            15,
        );

        return view('records.index', [
            'history' => $history,
            'campaign' => $campaign,
        ]);
    }
}
