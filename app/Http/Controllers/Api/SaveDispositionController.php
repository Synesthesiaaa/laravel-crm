<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveDispositionRequest;
use App\Models\DispositionCode;
use App\Services\DispositionService;
use Illuminate\Http\JsonResponse;

class SaveDispositionController extends Controller
{
    public function __invoke(SaveDispositionRequest $request, DispositionService $dispositionService): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $agent = $user->full_name ?? $user->name ?? $user->username ?? '';
        $campaign = (string) $validated['campaign_code'];
        $code = (string) $validated['disposition_code'];
        $label = $validated['disposition_label'] ?? null;
        $sessionId = isset($validated['call_session_id']) ? (int) $validated['call_session_id'] : null;

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
            isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            $validated['phone_number'] ?? null,
            ($validated['remarks'] ?? null) ?: ($validated['notes'] ?? null),
            isset($validated['call_duration_seconds']) ? (int) $validated['call_duration_seconds'] : null,
            $validated['lead_data_json'] ?? null,
        );
        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        return response()->json(['success' => true]);
    }
}
