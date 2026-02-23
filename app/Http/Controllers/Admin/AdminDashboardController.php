<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['name' => $campaign, 'forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $tableNames = $this->campaignService->getAllFormTableNames();

        $stats = [];
        foreach ($forms as $formCode => $formConfig) {
            $tableName = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
            if ($tableName === '' || !in_array($tableName, $tableNames, true)) {
                $stats[$formCode] = ['name' => $formConfig['name'] ?? $formCode, 'count' => 0, 'color' => $formConfig['color'] ?? 'blue'];
                continue;
            }
            try {
                $count = DB::table($tableName)->count();
            } catch (\Throwable) {
                $count = 0;
            }
            $stats[$formCode] = [
                'name' => $formConfig['name'] ?? $formCode,
                'count' => $count,
                'color' => $formConfig['color'] ?? 'blue',
            ];
        }

        $userCount = User::count();

        return view('admin.dashboard', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'stats' => $stats,
            'userCount' => $userCount,
            'user' => $request->user(),
        ]);
    }
}
