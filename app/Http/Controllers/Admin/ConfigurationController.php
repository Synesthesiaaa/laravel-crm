<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    public function __construct(protected CampaignService $campaignService) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'general');
        $campaigns = $this->campaignService->getCampaigns();
        return view('admin.configuration', ['tab' => $tab, 'campaigns' => $campaigns]);
    }
}
