<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampaignDispositionRecord;
use App\Repositories\DispositionRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispositionRecordsController extends Controller
{
    public function __construct(
        protected DispositionRepository $dispositionRepository
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $query = CampaignDispositionRecord::where('campaign_code', $campaign)->orderByDesc('called_at');
        if ($request->filled('agent')) {
            $query->where('agent', 'like', '%' . $request->input('agent') . '%');
        }
        if ($request->filled('disposition')) {
            $query->where('disposition_code', $request->input('disposition'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('called_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('called_at', '<=', $request->input('to_date'));
        }
        $records = $query->paginate(50);
        $dispositionCodes = $this->dispositionRepository->getForCampaign($campaign);
        return view('admin.disposition_records', [
            'records' => $records,
            'dispositionCodes' => $dispositionCodes,
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }
}
