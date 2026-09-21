<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordsController extends Controller
{
    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');

        return view('records.index', [
            'campaign' => $campaign,
        ]);
    }
}
