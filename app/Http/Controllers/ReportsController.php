<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $markup = view('reports.alpine-markup', [
            'campaign' => $request->session()->get('campaign', 'mbsales'),
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ])->render();

        return \Inertia\Inertia::render('Reports/Index', [
            'reportsMarkup' => $markup,
        ]);
    }
}
