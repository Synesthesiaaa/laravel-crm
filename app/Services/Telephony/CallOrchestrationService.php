<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\User;
use App\Services\DispositionService;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;

class CallOrchestrationService
{
    public function __construct(
        protected VicidialProxyService $vicidialProxy,
        protected CallStateService $callStateService,
        protected DispositionService $dispositionService
    ) {}

    /**
     * Start an outbound call: create session, originate via VICIdial, update state.
     * Blocks if agent has a call requiring disposition.
     *
     * @return OperationResult with data: ['session_id' => int] on success
     */
    public function startOutboundCall(User $user, string $campaign, string $phoneNumber, ?int $leadId = null, string $phoneCode = '1'): OperationResult
    {
        $active = CallSession::where('user_id', $user->id)->active()->first();
        if ($active) {
            return OperationResult::failure('Agent already has an active call. Hang up first.');
        }

        if ($this->dispositionService->hasPendingDisposition($user->id)) {
            return OperationResult::failure('Please save disposition for your last call before making a new one.');
        }

        $result = $this->vicidialProxy->execute($user, $campaign, 'external_dial', [
            'value' => $phoneNumber,
            'phone_code' => $phoneCode,
            'phone_number' => $phoneNumber,
        ]);

        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?? 'Dial failed');
        }

        try {
            $session = DB::transaction(function () use ($user, $campaign, $phoneNumber, $leadId) {
                $session = CallSession::create([
                    'user_id' => $user->id,
                    'campaign_code' => $campaign,
                    'lead_id' => $leadId,
                    'phone_number' => $phoneNumber,
                    'status' => CallSession::STATUS_DIALING,
                    'dialed_at' => now(),
                ]);

                $this->callStateService->transition($session, CallSession::STATUS_RINGING);

                return $session->fresh();
            });

            return OperationResult::success(['session_id' => $session->id]);
        } catch (\Throwable $e) {
            return OperationResult::failure($e->getMessage());
        }
    }

    /**
     * Record hangup for the agent's active call. Idempotent.
     */
    public function hangup(User $user, ?int $sessionId = null): OperationResult
    {
        $session = $sessionId
            ? CallSession::where('user_id', $user->id)->find($sessionId)
            : CallSession::where('user_id', $user->id)->active()->first();

        if (! $session) {
            return OperationResult::failure('No active call found');
        }

        return $this->callStateService->recordHangup($session, ['end_reason' => 'agent_hangup']);
    }

    /**
     * Get the agent's current active call session.
     */
    public function getActiveSession(User $user): ?CallSession
    {
        return CallSession::where('user_id', $user->id)->active()->first();
    }

    /**
     * Get the agent's pending disposition session (call ended, no disposition yet).
     */
    public function getPendingDispositionSession(User $user): ?CallSession
    {
        return $this->dispositionService->getPendingDispositionSession($user->id);
    }
}
