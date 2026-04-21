<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leads\StoreLeadListRequest;
use App\Http\Requests\Admin\Leads\UpdateLeadListRequest;
use App\Models\Campaign;
use App\Models\LeadList;
use App\Services\Leads\HopperLoaderService;
use App\Services\Leads\LeadListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadListsController extends Controller
{
    public function __construct(
        protected LeadListService $leadListService,
        protected HopperLoaderService $hopperLoader,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeadList::class);

        $currentCampaign = $request->session()->get('campaign', 'mbsales');
        $filterCampaign = $request->query('campaign', $currentCampaign);
        $campaigns = Campaign::active()->ordered()->get(['code', 'name']);

        $lists = LeadList::query()
            ->forCampaign($filterCampaign)
            ->withCount('leads')
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.leads.lists.index', [
            'lists' => $lists,
            'campaigns' => $campaigns,
            'filterCampaign' => $filterCampaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreLeadListRequest $request): RedirectResponse
    {
        $this->authorize('create', LeadList::class);

        $list = $this->leadListService->create($request->validated());

        return redirect()
            ->route('admin.leads.lists.show', $list)
            ->with('success', 'Lead list created.');
    }

    public function show(Request $request, LeadList $list): View
    {
        $this->authorize('view', $list);

        $list->loadCount('leads');

        return view('admin.leads.lists.show', [
            'list' => $list,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function update(UpdateLeadListRequest $request, LeadList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $this->leadListService->update($list, $request->validated());

        return redirect()
            ->route('admin.leads.lists.show', $list)
            ->with('success', 'Lead list updated.');
    }

    public function toggle(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('toggle', $list);

        $active = $request->boolean('active', ! $list->active);
        $result = $this->leadListService->toggleActive($list, $active);

        return redirect()->back()->with(
            $result->success ? 'success' : 'error',
            $result->message ?? ($active ? 'List enabled.' : 'List disabled.'),
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $list = LeadList::findOrFail((int) $request->input('id'));
        $this->authorize('delete', $list);

        $result = $this->leadListService->delete($list);

        return redirect()
            ->route('admin.leads.lists.index', ['campaign' => $list->campaign_code])
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function loadHopper(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('toggle', $list);

        $count = $this->hopperLoader->loadList($list);

        return redirect()->back()->with(
            'success',
            sprintf('Pushed %d lead(s) to the hopper.', $count),
        );
    }
}
