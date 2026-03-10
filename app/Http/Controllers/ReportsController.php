<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.index', [
            'campaign' => $request->session()->get('campaign', 'mbsales'),
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }
}
