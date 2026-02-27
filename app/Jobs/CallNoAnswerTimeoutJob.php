<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Services\Telephony\CallStateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched when call enters ringing state. If no answer within timeout, marks failed.
 */
class CallNoAnswerTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public int $callSessionId
    ) {
        $this->delay(now()->addSeconds(30));
        $this->onQueue('default');
    }

    public function handle(CallStateService $callStateService): void
    {
        $session = CallSession::find($this->callSessionId);
        if (!$session || $session->isTerminal()) {
            return;
        }

        if (in_array($session->status, [CallSession::STATUS_DIALING, CallSession::STATUS_RINGING], true)) {
            $result = $callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => 'no_answer_timeout',
            ]);
            if ($result->success) {
                Log::channel('telephony')->info('CallNoAnswerTimeout: Marked session as failed', [
                    'session_id' => $session->id,
                ]);
            }
        }
    }
}
