<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentCallDisposition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentSubmittedRecordsController extends Controller
{
    /**
     * Paginated list of call/disposition records submitted by this agent (reporting).
     */
    public function index(Request $request): JsonResponse
    {
        $campaign = (string) ($request->query('campaign') ?: $request->session()->get('campaign', 'mbsales'));
        $userId = (int) $request->user()->id;

        $query = AgentCallDisposition::query()
            ->select([
                'agent_call_dispositions.id',
                'agent_call_dispositions.called_at',
                'agent_call_dispositions.phone_number',
                'agent_call_dispositions.lead_pk',
                'agent_call_dispositions.disposition_code',
                'agent_call_dispositions.disposition_label',
                'agent_call_dispositions.disposition_source',
                'agent_call_dispositions.remarks',
                'agent_call_dispositions.capture_data',
                'agent_call_dispositions.call_duration_seconds',
            ])
            ->selectRaw('leads.status as lead_current_status')
            ->leftJoin('leads', 'leads.id', '=', 'agent_call_dispositions.lead_pk')
            ->where('agent_call_dispositions.campaign_code', $campaign)
            ->where('agent_call_dispositions.user_id', $userId)
            ->where('agent_call_dispositions.disposition_source', AgentCallDisposition::SOURCE_AGENT)
            ->orderByDesc('agent_call_dispositions.called_at')
            ->orderByDesc('agent_call_dispositions.id');

        if ($request->filled('disposition')) {
            $query->where('agent_call_dispositions.disposition_code', $request->query('disposition'));
        }
        if ($request->filled('lead_status')) {
            $query->where('leads.status', $request->query('lead_status'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('agent_call_dispositions.called_at', '>=', $request->query('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('agent_call_dispositions.called_at', '<=', $request->query('to_date'));
        }

        $paginator = $query->paginate(min(100, max(5, (int) $request->query('per_page', 25))))->withQueryString();

        $data = collect($paginator->items())->map(function ($row) {
            /** @var AgentCallDisposition $row */
            return [
                'id' => $row->id,
                'called_at' => $row->called_at?->toIso8601String(),
                'phone_number' => $row->phone_number,
                'lead_pk' => $row->lead_pk,
                'disposition_code' => $row->disposition_code,
                'disposition_label' => $row->disposition_label,
                'disposition_source' => $row->disposition_source,
                'lead_current_status' => $row->lead_current_status,
                'call_duration_seconds' => $row->call_duration_seconds,
                'remarks' => $row->remarks,
                'capture_data' => $row->capture_data ?? [],
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * CSV export (same filters as index).
     */
    public function export(Request $request): StreamedResponse|Response
    {
        $campaign = (string) ($request->query('campaign') ?: $request->session()->get('campaign', 'mbsales'));
        $userId = (int) $request->user()->id;

        $query = AgentCallDisposition::query()
            ->select([
                'agent_call_dispositions.id',
                'agent_call_dispositions.called_at',
                'agent_call_dispositions.phone_number',
                'agent_call_dispositions.lead_pk',
                'agent_call_dispositions.disposition_code',
                'agent_call_dispositions.disposition_label',
                'agent_call_dispositions.disposition_source',
                'agent_call_dispositions.remarks',
                'agent_call_dispositions.capture_data',
                'agent_call_dispositions.call_duration_seconds',
            ])
            ->selectRaw('leads.status as lead_current_status')
            ->leftJoin('leads', 'leads.id', '=', 'agent_call_dispositions.lead_pk')
            ->where('agent_call_dispositions.campaign_code', $campaign)
            ->where('agent_call_dispositions.user_id', $userId)
            ->where('agent_call_dispositions.disposition_source', AgentCallDisposition::SOURCE_AGENT)
            ->orderByDesc('agent_call_dispositions.called_at')
            ->orderByDesc('agent_call_dispositions.id');

        if ($request->filled('disposition')) {
            $query->where('agent_call_dispositions.disposition_code', $request->query('disposition'));
        }
        if ($request->filled('lead_status')) {
            $query->where('leads.status', $request->query('lead_status'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('agent_call_dispositions.called_at', '>=', $request->query('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('agent_call_dispositions.called_at', '<=', $request->query('to_date'));
        }

        $filename = 'my-submitted-records-'.$campaign.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'called_at', 'phone_number', 'lead_pk',
                'disposition_code', 'disposition_label', 'disposition_source',
                'lead_current_status', 'call_duration_seconds', 'remarks', 'capture_data_json',
            ]);
            foreach ($query->cursor() as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->called_at?->format('Y-m-d H:i:s'),
                    $row->phone_number,
                    $row->lead_pk,
                    $row->disposition_code,
                    $row->disposition_label,
                    $row->disposition_source,
                    $row->lead_current_status,
                    $row->call_duration_seconds,
                    $row->remarks,
                    json_encode($row->capture_data ?? []),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
