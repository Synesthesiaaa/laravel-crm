<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\VicidialAgentSession;
use App\Support\OperationResult;

/**
 * Syncs disposition to VICIdial when lead_id is available.
 * Uses Non-Agent API for lead update AND Agent API external_status
 * so the ViciDial agent session is properly advanced past the dispo screen.
 */
class VicidialDispositionSyncService
{
    public function __construct(
        protected VicidialProxyService $vicidialProxy,
        protected TelephonyLogger $telephonyLogger,
        protected VicidialNonAgentApiService $nonAgentApi,
    ) {}

    /**
     * Update remote lead/disposition state and report transport outcome safely.
     */
    public function syncDispositionToVicidial(CallSession $session): OperationResult
    {
        $viciCode = $this->mapLaravelToVicidial($session->disposition_code);

        if ($session->lead_id !== null) {
            $leadStatus = $this->syncLeadStatus($session, $viciCode);
            if (! $leadStatus->success) {
                return $leadStatus;
            }
        }

        $externalStatus = $this->sendExternalStatus($session, $viciCode);
        if (! $externalStatus->success) {
            return $externalStatus;
        }

        $restored = $this->restoreAgentAvailability($session);

        return OperationResult::success(null, null, ['availability_restore_failed' => ! $restored]);
    }

    private function syncLeadStatus(CallSession $session, string $viciCode): OperationResult
    {
        $params = [
            'search_method' => 'lead_id',
            'lead_id' => $session->lead_id,
            'status' => $viciCode,
        ];

        return $this->nonAgentApi->execute(null, $session->campaign_code, 'update_lead', $params, true);
    }

    /**
     * Call Agent API external_status so ViciDial moves the agent past disposition.
     */
    protected function sendExternalStatus(CallSession $session, string $viciCode): OperationResult
    {
        $user = $session->user;
        if (! $user) {
            return OperationResult::failure('Agent account is no longer available.', null, ['failure_code' => 'USER_NOT_FOUND']);
        }

        try {
            $result = $this->vicidialProxy->execute($user, $session->campaign_code, 'external_status', [
                'value' => $viciCode,
            ]);

            if (! $result['success']) {
                $this->telephonyLogger->warning('VicidialDispositionSyncService', 'external_status failed', [
                    'session_id' => $session->id,
                    'failure_code' => $result['failure_code'] ?? 'REMOTE_OPERATION_FAILED',
                    'outcome_unknown' => (bool) ($result['outcome_unknown'] ?? false),
                ]);

                return OperationResult::failure($result['message'] ?: 'Unable to sync disposition status.', null, [
                    'failure_code' => $result['failure_code'] ?? 'REMOTE_OPERATION_FAILED',
                    'outcome_unknown' => (bool) ($result['outcome_unknown'] ?? false),
                ]);
            }

            return OperationResult::success();
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('VicidialDispositionSyncService', 'external_status exception (non-blocking)', [
                'session_id' => $session->id,
                'exception_class' => $e::class,
            ]);

            return OperationResult::failure('VICIdial disposition outcome is unknown.', null, [
                'failure_code' => 'VICIDIAL_OUTCOME_UNKNOWN',
                'outcome_unknown' => true,
            ]);
        }
    }

    private function restoreAgentAvailability(CallSession $session): bool
    {
        $statusBeforeCall = strtolower((string) data_get($session->metadata, 'vicidial_session_status_before_call', ''));
        if ($statusBeforeCall === 'paused') {
            return true;
        }

        $user = $session->user;
        if (! $user) {
            return false;
        }

        try {
            $result = $this->vicidialProxy->execute($user, $session->campaign_code, 'external_pause', [
                'value' => 'RESUME',
            ]);

            if (! $result['success']) {
                $this->telephonyLogger->warning('VicidialDispositionSyncService', 'Agent resume failed after disposition', [
                    'session_id' => $session->id,
                    'failure_code' => $result['failure_code'] ?? 'REMOTE_OPERATION_FAILED',
                ]);

                return false;
            }

            VicidialAgentSession::query()
                ->where('user_id', $session->user_id)
                ->where('campaign_code', $session->campaign_code)
                ->update([
                    'session_status' => 'ready',
                    'pause_code' => null,
                    'last_synced_at' => now(),
                ]);

            return true;
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('VicidialDispositionSyncService', 'Agent resume exception after disposition', [
                'session_id' => $session->id,
                'exception_class' => $e::class,
            ]);

            return false;
        }
    }

    protected function mapLaravelToVicidial(string $code): string
    {
        $map = config('vicidial.disposition_map', []);

        return $map[$code] ?? $code;
    }
}
