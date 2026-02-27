<?php

namespace App\Services;

use App\Events\DispositionSaved;
use App\Models\CallSession;
use App\Models\CampaignDispositionRecord;
use App\Models\DispositionCode;
use App\Repositories\DispositionRepository;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialDispositionSyncService;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;

class DispositionService
{
    public function __construct(
        protected DispositionRepository $dispositionRepository,
        protected VicidialDispositionSyncService $vicidialSync,
        protected CallStateService $callStateService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    public function getCodesForCampaign(string $campaignCode): array
    {
        return $this->dispositionRepository->getForCampaign($campaignCode)
            ->map(fn ($c) => ['code' => $c->code, 'label' => $c->label, 'sort_order' => $c->sort_order])
            ->all();
    }

    /**
     * Save disposition. Validates code, links to call session, updates CallSession atomically.
     * Prevents duplicate submission per call session. Fires DispositionSaved.
     */
    public function saveDisposition(
        string $campaignCode,
        string $agent,
        string $dispositionCode,
        string $dispositionLabel,
        ?int $userId,
        ?int $callSessionId = null,
        ?int $leadId = null,
        ?string $phoneNumber = null,
        ?string $remarks = null,
        ?int $callDurationSeconds = null,
        ?string $leadDataJson = null
    ): OperationResult {
        $code = $this->resolveAndValidateCode($campaignCode, $dispositionCode, $dispositionLabel);
        if (! $code) {
            return OperationResult::failure('Invalid or inactive disposition code for this campaign.');
        }

        if ($callSessionId !== null) {
            $session = CallSession::where('id', $callSessionId)->where('user_id', $userId)->first();
            if (! $session) {
                return OperationResult::failure('Call session not found or access denied.');
            }

            // Safety net: if the session is somehow still active (e.g. hangup failed to
            // transition correctly), force it to completed so disposition can proceed.
            if (! $session->isTerminal()) {
                $this->telephonyLogger->warning('DispositionService', 'Session not terminal on save, forcing completed', [
                    'session_id' => $session->id,
                    'status' => $session->status,
                ]);
                $this->callStateService->transition($session, CallSession::STATUS_COMPLETED, [
                    'end_reason' => 'force_ended_on_disposition',
                ], true);
                $session->refresh();

                // After force, confirm it is now terminal
                if (! $session->isTerminal()) {
                    return OperationResult::failure('Call must be ended before submitting disposition.');
                }
            }

            if ($session->disposition_code !== null) {
                return OperationResult::failure('Disposition already submitted for this call.');
            }
            if (CampaignDispositionRecord::where('call_session_id', $callSessionId)->exists()) {
                return OperationResult::failure('Disposition already submitted for this call.');
            }
        }

        try {
            DB::transaction(function () use (
                $campaignCode,
                $agent,
                $dispositionCode,
                $dispositionLabel,
                $callSessionId,
                $leadId,
                $phoneNumber,
                $remarks,
                $callDurationSeconds,
                $leadDataJson
            ) {
                $record = CampaignDispositionRecord::create([
                    'call_session_id' => $callSessionId,
                    'campaign_code' => $campaignCode,
                    'agent' => $agent,
                    'disposition_code' => $dispositionCode,
                    'disposition_label' => $dispositionLabel,
                    'lead_id' => $leadId,
                    'phone_number' => $phoneNumber,
                    'remarks' => $remarks,
                    'call_duration_seconds' => $callDurationSeconds,
                    'lead_data_json' => $leadDataJson ? (is_string($leadDataJson) ? json_decode($leadDataJson, true) : $leadDataJson) : null,
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

                event(new DispositionSaved($campaignCode, $agent, $dispositionCode, $leadId));
            });

            return OperationResult::success();
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('DispositionService', 'Save failed', [
                'error' => $e->getMessage(),
                'campaign' => $campaignCode,
                'session_id' => $callSessionId,
            ]);

            return OperationResult::failure($e->getMessage());
        }
    }

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

    /**
     * End reasons that indicate the call was closed by the system, not by a real
     * agent interaction. These sessions never require a disposition.
     */
    protected const SKIP_DISPOSITION_END_REASONS = [
        'stale_session',           // auto-cleaned by stale detection
        'user_logout',             // cleaned on logout
        'ami_originate_failed',    // AMI couldn't dial – call never placed
        'reconciliation_timeout',  // cleaned by reconciliation job
        'force_ended_on_disposition', // safety net force-close (already saving disposition)
    ];

    /**
     * Pending disposition sessions older than this are ignored (no longer block).
     */
    protected const PENDING_DISPOSITION_MAX_AGE_HOURS = 24;

    public function hasPendingDisposition(int $userId): bool
    {
        return $this->pendingDispositionQuery($userId)->exists();
    }

    public function getPendingDispositionSession(int $userId): ?CallSession
    {
        return $this->pendingDispositionQuery($userId)
            ->orderByDesc('ended_at')
            ->first();
    }

    protected function pendingDispositionQuery(int $userId)
    {
        return CallSession::where('user_id', $userId)
            ->whereIn('status', CallSession::TERMINAL_STATUSES)
            ->whereNull('disposition_code')
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', now()->subHours(self::PENDING_DISPOSITION_MAX_AGE_HOURS))
            ->where(function ($q) {
                $q->whereNull('end_reason')
                    ->orWhereNotIn('end_reason', self::SKIP_DISPOSITION_END_REASONS);
            });
    }
}
