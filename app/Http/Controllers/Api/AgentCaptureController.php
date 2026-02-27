<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCaptureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_code' => ['required', 'string', 'max:50'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'lead_id' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'capture_data' => ['required', 'array'],
        ]);

        $campaign = $request->input('campaign_code');
        $allowedKeys = AgentScreenField::forCampaign($campaign)
            ->pluck('field_key')
            ->toArray();

        $captureData = [];
        foreach ($request->input('capture_data', []) as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $captureData[$key] = is_string($value) ? $value : (string) $value;
            }
        }

        $user = $request->user();

        $record = AgentCaptureRecord::create([
            'campaign_code' => $campaign,
            'call_session_id' => $request->input('call_session_id'),
            'lead_id' => $request->input('lead_id'),
            'phone_number' => $request->input('phone_number'),
            'agent' => $user->username ?? $user->full_name ?? (string) $user->id,
            'user_id' => $user->id,
            'capture_data' => $captureData,
        ]);

        return response()->json([
            'success' => true,
            'id' => $record->id,
        ]);
    }
}
