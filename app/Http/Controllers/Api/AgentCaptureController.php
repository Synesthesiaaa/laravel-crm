<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use App\Services\Telephony\TelephonySyncOutboxService;
use App\Support\FormFieldVisibility;
use App\Support\PercentageValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentCaptureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'campaign_code' => ['required', 'string', 'max:50'],
            'call_session_id' => [
                'nullable',
                'integer',
                Rule::exists('call_sessions', 'id')->where('user_id', $user->id),
            ],
            'lead_id' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'capture_data' => ['required', 'array'],
            'visible_fields' => ['nullable', 'array'],
            'visible_fields.*' => ['string', 'max:100'],
        ]);

        $campaign = $request->input('campaign_code');
        $fields = AgentScreenField::query()
            ->forCampaign($campaign)
            ->get(['field_key', 'vici_field', 'field_type', 'direction', 'is_required', 'visibility']);
        $fieldsByKey = $fields->keyBy('field_key');
        $allowedKeys = $fieldsByKey->keys()->toArray();
        $submittedValues = $request->input('capture_data', []);
        $visibleKeys = $fields
            ->filter(fn (AgentScreenField $field): bool => FormFieldVisibility::isVisible($field->visibility, $submittedValues))
            ->pluck('field_key')
            ->map(fn (mixed $fieldKey): string => (string) $fieldKey)
            ->all();

        $captureData = [];
        foreach ($submittedValues as $key => $value) {
            if (in_array($key, $allowedKeys, true) && in_array($key, $visibleKeys, true)) {
                $field = $fieldsByKey->get($key);
                $captureData[$key] = $this->normalizeCaptureValue($value, (string) ($field->field_type ?? 'text'));
            }
        }

        $this->validateRequiredCaptureData($fields, $visibleKeys, $captureData);

        $leadFields = $this->postFieldsForVicidial($fields, $captureData, trim((string) $request->input('lead_id', '')));
        $record = DB::transaction(function () use ($request, $user, $campaign, $captureData, $leadFields): AgentCaptureRecord {
            $record = AgentCaptureRecord::create([
                'campaign_code' => $campaign,
                'call_session_id' => $request->input('call_session_id'),
                'lead_id' => $request->input('lead_id'),
                'phone_number' => $request->input('phone_number'),
                'agent' => $user->username ?? $user->full_name ?? (string) $user->id,
                'user_id' => $user->id,
                'capture_data' => $captureData,
            ]);

            if ($leadFields !== []) {
                app(TelephonySyncOutboxService::class)->record(
                    TelephonySyncOutboxService::OPERATION_LEAD_FIELDS,
                    'agent-capture:'.$record->id.':lead-fields',
                    [
                        'user_id' => $user->id,
                        'campaign' => (string) $campaign,
                        'fields' => $leadFields,
                    ],
                );
            }

            return $record;
        });

        if ($leadFields !== []) {
            try {
                app(TelephonySyncOutboxService::class)->dispatchPending();
            } catch (\Throwable $dispatchException) {
                Log::channel('telephony')->warning('Agent capture VICIdial sync remains pending for dispatcher', [
                    'capture_id' => $record->id,
                    'exception_class' => $dispatchException::class,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'id' => $record->id,
        ]);
    }

    /**
     * @param  array<string, string>  $captureData
     */
    private function validateRequiredCaptureData(Collection $fields, array $visibleKeys, array $captureData): void
    {
        $errors = [];

        foreach ($fields as $field) {
            if (! (bool) ($field->is_required ?? false)) {
                continue;
            }

            $fieldKey = (string) $field->field_key;
            if (! in_array($fieldKey, $visibleKeys, true)) {
                continue;
            }

            if (trim((string) ($captureData[$fieldKey] ?? '')) === '') {
                $errors["capture_data.{$fieldKey}"] = 'This field is required.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Push mapped capture fields back to Vicidial for directions post/both.
     */
    private function postFieldsForVicidial(Collection $fields, array $captureData, string $leadId): array
    {
        if ($leadId === '') {
            return [];
        }

        $catalog = (array) config('vicidial_fields.fields', []);
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
            return [];
        }

        return $updateFields;
    }

    private function normalizeCaptureValue(mixed $value, string $fieldType): string
    {
        if ($fieldType === 'percentage') {
            return PercentageValue::normalize($value) ?? '';
        }

        return is_string($value) ? $value : (string) $value;
    }
}
