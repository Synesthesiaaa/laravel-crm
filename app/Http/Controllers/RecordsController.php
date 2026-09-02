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
        $history = $this->callHistoryService->getHistoricalHistory(
            $request->user(),
            $campaign,
            $request->query(),
            true,
            15,
        );

        return view('records.index', [
            'history' => $history->records,
            'historyPage' => $history,
            'campaign' => $campaign,
        ]);
    }
}
