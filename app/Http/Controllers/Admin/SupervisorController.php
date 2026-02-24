<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispositionCode;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function index(): View
    {
        $campaign = session('campaign');

        $dispositionCodes = DispositionCode::where(function ($q) use ($campaign) {
            $q->where('campaign_code', $campaign)->orWhereNull('campaign_code');
        })->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.supervisor', compact('dispositionCodes'));
    }
}
