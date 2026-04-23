<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentCallDisposition;
use App\Models\DispositionCode;
use App\Repositories\DispositionRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentCallDispositionsController extends Controller
{
    public function __construct(
        protected DispositionRepository $dispositionRepository,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $query = AgentCallDisposition::query()->forCampaign($campaign)->orderByDesc('called_at');
        if ($request->filled('agent')) {
            $query->where('agent', 'like', '%'.$request->input('agent').'%');
        }
        if ($request->filled('disposition')) {
            $query->where('disposition_code', $request->input('disposition'));
        }
        if ($request->filled('source')) {
            $query->where('disposition_source', $request->input('source'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('called_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('called_at', '<=', $request->input('to_date'));
        }

        $records = $query->paginate(50);
        $dispositionCodes = $this->dispositionRepository->getForCampaign($campaign);

        return view('admin.agent_call_dispositions', [
            'records' => $records,
            'dispositionCodes' => $dispositionCodes,
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function edit(Request $request, AgentCallDisposition $record): View|RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        if ($record->campaign_code !== $campaign) {
            return redirect()->route('admin.agent-records.index')->with('error', 'Record belongs to another campaign.');
        }

        $dispositionCodes = $this->dispositionRepository->getForCampaign($campaign);

        return view('admin.agent_call_dispositions_edit', [
            'record' => $record,
            'dispositionCodes' => $dispositionCodes,
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function update(Request $request, AgentCallDisposition $record): RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        if ($record->campaign_code !== $campaign) {
            return redirect()->route('admin.agent-records.index')->with('error', 'Record belongs to another campaign.');
        }

        $validated = $request->validate([
            'disposition_code' => ['required', 'string', 'max:80'],
            'disposition_label' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:10000'],
            'call_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'capture' => ['nullable', 'array'],
            'capture.*' => ['nullable', 'string', 'max:10000'],
        ]);

        $exists = DispositionCode::where(function ($q) use ($campaign) {
            $q->where('campaign_code', $campaign)->orWhere('campaign_code', '');
        })->where('code', $validated['disposition_code'])->where('is_active', true)->exists();

        if (! $exists) {
            return back()->with('error', 'Invalid disposition code for this campaign.');
        }

        $capture = $record->capture_data ?? [];
        if (isset($validated['capture']) && is_array($validated['capture'])) {
            foreach ($validated['capture'] as $k => $v) {
                $capture[(string) $k] = $v;
            }
        }

        $record->update([
            'disposition_code' => $validated['disposition_code'],
            'disposition_label' => $validated['disposition_label'] ?? $record->disposition_label,
            'remarks' => $validated['remarks'] ?? null,
            'call_duration_seconds' => $validated['call_duration_seconds'] ?? $record->call_duration_seconds,
            'capture_data' => $capture,
            'last_edited_by_user_id' => $request->user()->id,
            'last_edited_at' => now(),
        ]);

        return redirect()->route('admin.agent-records.index')->with('success', 'Record updated.');
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $query = AgentCallDisposition::query()->forCampaign($campaign)->orderBy('id');

        if ($request->filled('from_date')) {
            $query->whereDate('called_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('called_at', '<=', $request->input('to_date'));
        }

        $filename = 'agent-call-dispositions-'.$campaign.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'called_at', 'phone_number', 'lead_pk', 'agent', 'disposition_code',
                'disposition_label', 'disposition_source', 'remarks', 'call_duration_seconds', 'capture_data_json',
            ]);
            foreach ($query->cursor() as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->called_at?->format('Y-m-d H:i:s'),
                    $row->phone_number,
                    $row->lead_pk,
                    $row->agent,
                    $row->disposition_code,
                    $row->disposition_label,
                    $row->disposition_source,
                    $row->remarks,
                    $row->call_duration_seconds,
                    json_encode($row->capture_data ?? []),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
