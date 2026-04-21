<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Http\Controllers\Controller;
use App\Jobs\ImportLeadsFileJob;
use App\Models\LeadList;
use App\Services\Leads\LeadFieldService;
use App\Services\Leads\LeadImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadImportController extends Controller
{
    public function __construct(
        protected LeadImportService $importService,
        protected LeadFieldService $fieldService,
    ) {}

    public function form(Request $request, LeadList $list): View
    {
        $this->authorize('import', $list);

        return view('admin.leads.import.upload', [
            'list' => $list,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function upload(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('import', $list);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:51200'],
        ]);

        try {
            $stash = $this->importService->stash($request->file('file'), $list->campaign_code);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Upload failed: '.$e->getMessage());
        }

        $request->session()->put('lead_import_'.$list->id, [
            'token' => $stash['token'],
            'headers' => $stash['headers'],
            'preview' => $stash['preview'],
            'rows' => $stash['rows'],
        ]);

        return redirect()->route('admin.leads.import.mapping', $list);
    }

    public function mapping(Request $request, LeadList $list): View|RedirectResponse
    {
        $this->authorize('import', $list);

        $stash = $request->session()->get('lead_import_'.$list->id);
        if (! $stash) {
            return redirect()->route('admin.leads.import.form', $list)->with('error', 'Upload a file first.');
        }

        $fields = $this->fieldService->getFields($list->campaign_code)
            ->filter(fn ($f) => $f->importable)
            ->values();

        return view('admin.leads.import.mapping', [
            'list' => $list,
            'fields' => $fields,
            'stash' => $stash,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function confirm(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('import', $list);

        $stash = $request->session()->get('lead_import_'.$list->id);
        if (! $stash) {
            return redirect()->route('admin.leads.import.form', $list)->with('error', 'Upload a file first.');
        }

        $request->validate([
            'mapping' => ['required', 'array'],
            'dedupe' => ['nullable', 'in:phone_number,vendor_lead_code'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        $mapping = collect($request->input('mapping', []))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->all();

        $hasPhone = in_array('phone_number', $mapping, true);
        if (! $hasPhone) {
            return redirect()->back()->with('error', 'At least one column must map to phone_number.');
        }

        ImportLeadsFileJob::dispatch(
            $list->id,
            $stash['token'],
            $mapping,
            [
                'dedupe' => $request->input('dedupe'),
                'update_existing' => $request->boolean('update_existing'),
            ],
            (int) $request->user()->id,
        );

        $request->session()->forget('lead_import_'.$list->id);

        return redirect()
            ->route('admin.leads.lists.show', $list)
            ->with('success', 'Import queued. You will be notified when it completes.');
    }
}
