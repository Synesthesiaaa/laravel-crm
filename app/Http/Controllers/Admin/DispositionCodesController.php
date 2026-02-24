<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDispositionCodeRequest;
use App\Http\Requests\Admin\UpdateDispositionCodeRequest;
use App\Models\DispositionCode;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispositionCodesController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function index(Request $request): View
    {
        $campaign       = $request->session()->get('campaign', 'mbsales');
        $filterCampaign = $request->query('campaign', $campaign);
        $campaigns      = $this->campaignService->getCampaigns();
        $codes          = DispositionCode::where('campaign_code', $filterCampaign === '' ? '' : $filterCampaign)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        return view('admin.disposition_codes', [
            'codes'          => $codes,
            'campaigns'      => $campaigns,
            'filterCampaign' => $filterCampaign,
            'campaignName'   => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreDispositionCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        DispositionCode::create([
            'campaign_code' => $validated['campaign_code'],
            'code'          => $validated['code'],
            'label'         => $validated['label'],
            'sort_order'    => $validated['sort_order'] ?? 0,
            'is_active'     => true,
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.disposition-codes.index', ['campaign' => $validated['campaign_code']])
            ->with('success', 'Disposition code created.');
    }

    public function update(UpdateDispositionCodeRequest $request, int $id): RedirectResponse
    {
        $code      = DispositionCode::findOrFail($id);
        $validated = $request->validated();
        $code->update([
            'code'       => $validated['code'],
            'label'      => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? $code->sort_order,
            'is_active'  => $request->boolean('is_active', true),
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.disposition-codes.index', ['campaign' => $code->campaign_code])
            ->with('success', 'Disposition code updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $id      = (int) $request->input('id');
        $code    = DispositionCode::findOrFail($id);
        $campaign = $code->campaign_code;
        $code->delete();
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.disposition-codes.index', ['campaign' => $campaign])
            ->with('success', 'Disposition code deleted.');
    }
}
