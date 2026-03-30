<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DispositionCode;
use App\Services\DispositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveDispositionController extends Controller
{
    /**
     * Persist disposition in CRM and trigger server-mediated VICIdial sync when applicable.
     *
     * **200:** `{ "success": true, "vicidial_sync": { "status": "skipped"|"synced"|"partial"|"failed", "message"?: string } }`
     * — `message` is user-safe (no dialer secrets or raw API bodies).
     *
     * **422:** `{ "success": false, "message": string }` on validation or business rule failure.
     */
    public function __invoke(Request $request, DispositionService $dispositionService): JsonResponse
    {
        $request->validate([
            'campaign_code' => ['required', 'string'],
            'disposition_code' => ['required', 'string'],
            'disposition_label' => ['nullable', 'string'],
            'call_session_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $user = $request->user();
        $agent = $user->full_name ?? $user->name ?? $user->username ?? '';
        $campaign = $request->input('campaign_code');
        $code = $request->input('disposition_code');
        $label = $request->input('disposition_label');
        $sessionId = $request->input('call_session_id') ? (int) $request->input('call_session_id') : null;

        if (empty($label)) {
            $d = DispositionCode::where(function ($q) use ($campaign) {
                $q->where('campaign_code', $campaign)->orWhere('campaign_code', '');
            })->where('code', $code)->where('is_active', true)->first();
            $label = $d?->label ?? $code;
        }

        $result = $dispositionService->saveDisposition(
            $campaign,
            $agent,
            $code,
            $label,
            $user->id,
            $sessionId,
            $request->input('lead_id') ? (int) $request->input('lead_id') : null,
            $request->input('phone_number'),
            $request->input('remarks') ?: $request->input('notes'),
            $request->input('call_duration_seconds') ? (int) $request->input('call_duration_seconds') : null,
            $request->input('lead_data_json'),
        );
        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        $payload = ['success' => true];
        if (is_array($result->data) && isset($result->data['vicidial_sync'])) {
            $payload['vicidial_sync'] = $result->data['vicidial_sync'];
        }

        return response()->json($payload);
    }
}
