<?php

namespace App\Services;

use App\Models\CallSession;
use App\Repositories\DispositionRepository;
use App\Support\OperationResult;

class DispositionService
{
    public function __construct(
        protected DispositionRepository $dispositionRepository,
        protected AgentCallDispositionService $agentCallDispositionService,
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
        ?string $leadDataJson = null,
    ): OperationResult {
        return $this->agentCallDispositionService->saveDisposition(
            $campaignCode,
            $agent,
            $dispositionCode,
            $dispositionLabel,
            $userId,
            $callSessionId,
            $leadId,
            $phoneNumber,
            $remarks,
            $callDurationSeconds,
            $leadDataJson,
            null,
        );
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
