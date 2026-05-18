<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use App\Services\Telephony\LeadService;
use App\Services\Telephony\TelephonyLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentCaptureController extends Controller
{
    public function __construct(
        protected LeadService $leadService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

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
        $fields = AgentScreenField::query()
            ->forCampaign($campaign)
            ->get(['field_key', 'vici_field', 'direction']);
        $allowedKeys = $fields->pluck('field_key')->toArray();

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

        $this->syncPostFieldsToVicidial($request, $fields, $captureData, (string) $campaign);

        return response()->json([
            'success' => true,
            'id' => $record->id,
        ]);
    }

    /**
     * Push mapped capture fields back to Vicidial for directions post/both.
     */
    private function syncPostFieldsToVicidial(Request $request, Collection $fields, array $captureData, string $campaign): void
    {
        $leadId = trim((string) $request->input('lead_id', ''));
        if ($leadId === '') {
            return;
        }

        $catalog = (array) config('vicidial_fields', []);
        $updateFields = ['lead_id' => $leadId];

        foreach ($fields as $field) {
            $direction = strtolower(trim((string) ($field->direction ?? 'get')));
            if (! in_array($direction, ['post', 'both'], true)) {
                continue;
            }

            $fieldKey = (string) $field->field_key;
            $viciField = trim((string) ($field->vici_field ?? ''));
            if ($viciField === '' || ! array_key_exists($fieldKey, $captureData)) {
                continue;
            }

            $meta = $catalog[$viciField] ?? null;
            if (! is_array($meta) || ! (bool) ($meta['writeable'] ?? false)) {
                continue;
            }

            $updateFields[$viciField] = (string) $captureData[$fieldKey];
        }

        if (count($updateFields) <= 1) {
            return;
        }

        try {
            $result = $this->leadService->updateFields($request->user(), $campaign, $updateFields);
            if (! $result->success) {
                $this->telephonyLogger->warning('AgentCaptureController', 'Vicidial update_fields push failed', [
                    'campaign' => $campaign,
                    'lead_id' => $leadId,
                    'message' => $result->message,
                    'mapped_fields' => array_keys($updateFields),
                ]);
            }
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('AgentCaptureController', 'Vicidial update_fields push threw exception', [
                'campaign' => $campaign,
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
