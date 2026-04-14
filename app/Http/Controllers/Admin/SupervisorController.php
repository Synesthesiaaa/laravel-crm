<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispositionCode;
use Inertia\Response;

class SupervisorController extends Controller
{
    public function index(): Response
    {
        $campaign = session('campaign');

        $dispositionCodes = DispositionCode::where(function ($q) use ($campaign) {
            $q->where('campaign_code', $campaign)->orWhereNull('campaign_code');
        })->where('is_active', true)->orderBy('sort_order')->get();

        return $this->inertiaAdmin('admin.inline-supervisor', compact('dispositionCodes'), 'Supervisor');
    }
}
