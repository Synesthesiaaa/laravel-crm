<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgentCallDispositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveAgentRecordController extends Controller
{
    public function __invoke(Request $request, AgentCallDispositionService $service): JsonResponse
    {
        $request->validate([
            'campaign_code' => ['required', 'string', 'max:50'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'lead_pk' => ['nullable', 'integer', 'exists:leads,id'],
            'lead_id' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'disposition_code' => ['required', 'string'],
            'disposition_label' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'call_duration_seconds' => ['nullable', 'integer'],
            'capture_data' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $leadPk = $request->input('lead_pk') ? (int) $request->input('lead_pk') : null;
        if ($leadPk === null && $request->filled('lead_id') && is_numeric($request->input('lead_id'))) {
            $leadPk = (int) $request->input('lead_id');
        }

        $result = $service->saveUnifiedRecord(
            $user,
            (string) $request->input('campaign_code'),
            $request->input('call_session_id') ? (int) $request->input('call_session_id') : null,
            $leadPk,
            $request->input('phone_number'),
            (string) $request->input('disposition_code'),
            $request->input('disposition_label'),
            $request->input('remarks') ?: $request->input('notes'),
            $request->input('capture_data', []) ?? [],
            $request->input('call_duration_seconds') ? (int) $request->input('call_duration_seconds') : null,
        );

        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        return response()->json(['success' => true]);
    }
}
