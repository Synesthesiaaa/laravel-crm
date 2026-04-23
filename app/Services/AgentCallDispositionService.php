<?php

namespace App\Services;

use App\Events\DispositionSaved;
use App\Models\AgentCallDisposition;
use App\Models\AgentScreenField;
use App\Models\CallSession;
use App\Models\DispositionCode;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\User;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialDispositionSyncService;
use App\Support\LeadPayloadBuilder;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;

class AgentCallDispositionService
{
    public function __construct(
        protected VicidialDispositionSyncService $vicidialSync,
        protected CallStateService $callStateService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Full disposition save (legacy API + unified table): validates code, writes `agent_call_dispositions`,
     * updates `call_sessions`, lead + hopper, ViciDial write-back, broadcasts event.
     */
    public function saveDisposition(
        string $campaignCode,
        string $agent,
        string $dispositionCode,
        string $dispositionLabel,
        ?int $userId,
        ?int $callSessionId = null,
        ?int $leadPk = null,
        ?string $phoneNumber = null,
        ?string $remarks = null,
        ?int $callDurationSeconds = null,
        mixed $leadDataJson = null,
        ?array $captureData = null,
    ): OperationResult {
        $code = $this->resolveAndValidateCode($campaignCode, $dispositionCode, $dispositionLabel);
        if (! $code) {
            return OperationResult::failure('Invalid or inactive disposition code for this campaign.');
        }

        $dispositionCode = $code['code'];
        $dispositionLabel = $code['label'];

        if ($callSessionId !== null) {
            $session = CallSession::where('id', $callSessionId)->where('user_id', $userId)->first();
            if (! $session) {
                return OperationResult::failure('Call session not found or access denied.');
            }

            if (! $session->isTerminal()) {
                $this->telephonyLogger->warning('AgentCallDispositionService', 'Session not terminal on save, forcing completed', [
                    'session_id' => $session->id,
                    'status' => $session->status,
                ]);
                $this->callStateService->transition($session, CallSession::STATUS_COMPLETED, [
                    'end_reason' => 'force_ended_on_disposition',
                ], true);
                $session->refresh();

                if (! $session->isTerminal()) {
                    return OperationResult::failure('Call must be ended before submitting disposition.');
                }
            }

            if ($session->disposition_code !== null) {
                return OperationResult::failure('Disposition already submitted for this call.');
            }
            if (AgentCallDisposition::where('call_session_id', $callSessionId)->exists()) {
                return OperationResult::failure('Disposition already submitted for this call.');
            }
        }

        try {
            DB::transaction(function () use (
                $campaignCode,
                $agent,
                $dispositionCode,
                $dispositionLabel,
                $userId,
                $callSessionId,
                $leadPk,
                $phoneNumber,
                $remarks,
                $callDurationSeconds,
                $leadDataJson,
                $captureData,
            ) {
                $lead = $leadPk ? Lead::find($leadPk) : null;
                $sessionRow = $callSessionId ? CallSession::find($callSessionId) : null;

                $leadSnapshot = null;
                if ($lead) {
                    $leadSnapshot = LeadPayloadBuilder::snapshot($lead);
                }
                if (is_string($leadDataJson)) {
                    $decoded = json_decode($leadDataJson, true);
                    if (is_array($decoded)) {
                        $leadSnapshot = array_merge($leadSnapshot ?? [], $decoded);
                    }
                } elseif (is_array($leadDataJson)) {
                    $leadSnapshot = array_merge($leadSnapshot ?? [], $leadDataJson);
                }

                AgentCallDisposition::create([
                    'call_session_id' => $callSessionId,
                    'campaign_code' => $campaignCode,
                    'list_id' => $lead?->list_id,
                    'lead_pk' => $lead?->id,
                    'vicidial_lead_id' => $sessionRow?->vicidial_lead_id,
                    'phone_number' => $phoneNumber,
                    'user_id' => $userId,
                    'agent' => $agent,
                    'call_duration_seconds' => $callDurationSeconds,
                    'disposition_code' => $dispositionCode,
                    'disposition_label' => $dispositionLabel,
                    'disposition_source' => AgentCallDisposition::SOURCE_AGENT,
                    'remarks' => $remarks,
                    'capture_data' => $captureData,
                    'lead_snapshot' => $leadSnapshot,
                    'called_at' => now(),
                ]);

                if ($callSessionId !== null) {
                    $session = CallSession::lockForUpdate()->find($callSessionId);
                    if ($session && $session->disposition_code === null) {
                        $session->update([
                            'disposition_code' => $dispositionCode,
                            'disposition_label' => $dispositionLabel,
                            'disposition_remarks' => $remarks,
                            'disposition_at' => now(),
                            'call_duration_seconds' => $callDurationSeconds ?? $session->call_duration_seconds,
                        ]);
                        $this->vicidialSync->syncDispositionToVicidial($session->fresh());
                    }
                }

                if ($leadPk) {
                    $lockedLead = Lead::lockForUpdate()->find($leadPk);
                    if ($lockedLead) {
                        $lockedLead->increment('called_count');
                        $lockedLead->update([
                            'status' => $dispositionCode,
                            'last_called_at' => now(),
                            'last_local_call_time' => now(),
                        ]);
                    }

                    LeadHopper::where('lead_pk', $leadPk)
                        ->whereIn('status', ['pending', 'assigned'])
                        ->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);
                }

                event(new DispositionSaved($campaignCode, $agent, $dispositionCode, $leadPk));
            });

            return OperationResult::success();
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('AgentCallDispositionService', 'Save failed', [
                'error' => $e->getMessage(),
                'campaign' => $campaignCode,
                'session_id' => $callSessionId,
            ]);

            return OperationResult::failure($e->getMessage());
        }
    }

    /**
     * Unified agent save: capture fields + disposition in one request.
     */
    public function saveUnifiedRecord(
        User $agent,
        string $campaignCode,
        ?int $callSessionId,
        ?int $leadPk,
        ?string $phoneNumber,
        string $dispositionCode,
        ?string $dispositionLabel,
        ?string $remarks,
        array $captureDataRaw,
        ?int $callDurationSeconds,
    ): OperationResult {
        $allowedKeys = AgentScreenField::forCampaign($campaignCode)
            ->pluck('field_key')
            ->toArray();

        $captureData = [];
        foreach ($captureDataRaw as $key => $value) {
            if (in_array((string) $key, $allowedKeys, true)) {
                $captureData[(string) $key] = is_string($value) ? $value : (string) $value;
            }
        }

        if (empty($dispositionLabel)) {
            $d = DispositionCode::where(function ($q) use ($campaignCode) {
                $q->where('campaign_code', $campaignCode)->orWhere('campaign_code', '');
            })->where('code', $dispositionCode)->where('is_active', true)->first();
            $dispositionLabel = $d?->label ?? $dispositionCode;
        }

        $agentName = $agent->full_name ?? $agent->name ?? $agent->username ?? (string) $agent->id;

        return $this->saveDisposition(
            $campaignCode,
            $agentName,
            $dispositionCode,
            (string) $dispositionLabel,
            $agent->id,
            $callSessionId,
            $leadPk,
            $phoneNumber,
            $remarks,
            $callDurationSeconds,
            null,
            $captureData,
        );
    }

    /**
     * @return array{code: string, label: string}|null
     */
    protected function resolveAndValidateCode(string $campaignCode, string $code, ?string $label): ?array
    {
        $dc = DispositionCode::where(function ($q) use ($campaignCode) {
            $q->where('campaign_code', $campaignCode)->orWhere('campaign_code', '');
        })->where('code', $code)->where('is_active', true)->first();

        if (! $dc) {
            return null;
        }

        return ['code' => $dc->code, 'label' => $label ?: $dc->label];
    }
}
