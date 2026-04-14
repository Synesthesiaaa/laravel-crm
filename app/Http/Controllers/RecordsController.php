<?php

namespace App\Http\Controllers;

use App\Services\CallHistoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordsController extends Controller
{
    public function __construct(
        protected CallHistoryService $callHistoryService
    ) {}

    public function index(Request $request): Response
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $history = $this->callHistoryService->getHistoryForCampaign(
            $campaign,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('agent'),
            15
        );

        return Inertia::render('Records/Index', [
            'history' => $history,
            'filters' => [
                'start_date' => $request->query('start_date', ''),
                'end_date' => $request->query('end_date', ''),
                'agent' => $request->query('agent', ''),
            ],
        ]);
    }
}
