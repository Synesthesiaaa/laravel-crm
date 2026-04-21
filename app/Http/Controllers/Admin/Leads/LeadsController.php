<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leads\StoreLeadRequest;
use App\Http\Requests\Admin\Leads\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\LeadList;
use App\Services\Leads\LeadFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadsController extends Controller
{
    public function __construct(
        protected LeadFieldService $fieldService,
    ) {}

    public function index(Request $request, LeadList $list): View
    {
        $this->authorize('viewAny', Lead::class);

        $layout = $this->fieldService->getColumnLayout($list->campaign_code);

        $query = Lead::query()->forList($list->id);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('vendor_lead_code', 'like', "%{$search}%");
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }
        if (($enabled = $request->query('enabled')) !== null && $enabled !== '') {
            $query->where('enabled', (bool) $enabled);
        }

        $leads = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.leads.leads.index', [
            'list' => $list,
            'leads' => $leads,
            'columns' => $layout['columns'],
            'headers' => $layout['headers'],
            'filters' => [
                'q' => $request->query('q', ''),
                'status' => $request->query('status', ''),
                'enabled' => $request->query('enabled', ''),
            ],
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function create(Request $request, LeadList $list): View
    {
        $this->authorize('create', Lead::class);

        $fields = $this->fieldService->getFields($list->campaign_code);

        return view('admin.leads.leads.form', [
            'list' => $list,
            'lead' => null,
            'fields' => $fields,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreLeadRequest $request, LeadList $list): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $data = $request->validated();
        $data['campaign_code'] = $list->campaign_code;
        $data['list_id'] = $list->id;

        $lead = Lead::create($data);

        $list->update(['leads_count' => $list->leads()->count()]);

        return redirect()
            ->route('admin.leads.leads.index', $list)
            ->with('success', "Lead #{$lead->id} created.");
    }

    public function edit(Request $request, LeadList $list, Lead $lead): View
    {
        $this->authorize('update', $lead);
        abort_if($lead->list_id !== $list->id, 404);

        $fields = $this->fieldService->getFields($list->campaign_code);

        return view('admin.leads.leads.form', [
            'list' => $list,
            'lead' => $lead,
            'fields' => $fields,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function update(UpdateLeadRequest $request, LeadList $list, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        abort_if($lead->list_id !== $list->id, 404);

        $lead->update($request->validated());

        return redirect()
            ->route('admin.leads.leads.index', $list)
            ->with('success', "Lead #{$lead->id} updated.");
    }

    public function destroy(Request $request, LeadList $list): RedirectResponse
    {
        $lead = Lead::where('list_id', $list->id)->findOrFail((int) $request->input('id'));
        $this->authorize('delete', $lead);

        $lead->delete();
        $list->update(['leads_count' => $list->leads()->count()]);

        return redirect()
            ->route('admin.leads.leads.index', $list)
            ->with('success', "Lead #{$lead->id} deleted.");
    }

    public function bulk(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('bulkUpdate', Lead::class);

        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->all();
        $action = (string) $request->input('action', '');

        if ($ids === [] || $action === '') {
            return redirect()->back()->with('error', 'Select at least one lead and an action.');
        }

        $query = Lead::query()->forList($list->id)->whereIn('id', $ids);

        $affected = match ($action) {
            'enable' => $query->update(['enabled' => true]),
            'disable' => $query->update(['enabled' => false]),
            'mark_dnc' => $query->update(['status' => 'DNC', 'enabled' => false]),
            'reset_status' => $query->update(['status' => 'NEW', 'called_count' => 0]),
            'delete' => $query->delete(),
            default => 0,
        };

        $list->update(['leads_count' => $list->leads()->count()]);

        return redirect()->back()->with('success', "Bulk action applied to {$affected} lead(s).");
    }
}
