<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DispositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveDispositionController extends Controller
{
    public function __invoke(Request $request, DispositionService $dispositionService): JsonResponse
    {
        $request->validate([
            'campaign_code' => ['required', 'string'],
            'disposition_code' => ['required', 'string'],
            'disposition_label' => ['required', 'string'],
        ]);
        $user = $request->user();
        $agent = $user->full_name ?? $user->name ?? $user->username ?? '';
        $result = $dispositionService->saveDisposition(
            $request->input('campaign_code'),
            $agent,
            $request->input('disposition_code'),
            $request->input('disposition_label'),
            $request->input('lead_id') ? (int) $request->input('lead_id') : null,
            $request->input('phone_number'),
            $request->input('remarks'),
            $request->input('call_duration_seconds') ? (int) $request->input('call_duration_seconds') : null,
            $request->input('lead_data_json')
        );
        if (!$result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }
        return response()->json(['success' => true]);
    }
}
