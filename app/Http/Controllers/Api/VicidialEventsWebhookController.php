<?php

namespace App\Http\Controllers\Api;

use App\Events\InboundCallReceived;
use App\Events\VicidialAgentEvent;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\TelephonyLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives ViciDial Agent Push Events.
 * Configure Agent Push URL in ViciDial System Settings:
 *   get2post.php?HTTPURLTOPOST=http://CRM:8000/api/webhooks/vicidial-events
 *     ?user=--A--user--B--&event=--A--event--B--&message=--A--message--B--
 *     &lead_id=--A--lead_id--B--&counter=--A--counter--B--
 */
class VicidialEventsWebhookController extends Controller
{
    public function __construct(
        protected CallStateService $callStateService,
        protected TelephonyLogger $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('vicidial.events_webhook_secret', '');
        if ($secret !== '' && $request->header('X-Webhook-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $viciUser = $request->input('user', '');
        $event = $request->input('event', '');
        $message = $request->input('message', '');
        $leadId = $request->input('lead_id');
        $counter = $request->input('counter');

        if ($event === '' || $viciUser === '') {
            return response()->json(['received' => true, 'processed' => false, 'reason' => 'missing event or user']);
        }

        $this->logger->event('VicidialEventsWebhook', $event, 'ViciDial push event received', [
            'vici_user' => $viciUser,
            'message' => $message,
            'lead_id' => $leadId,
        ]);

        $user = User::where('vici_user', $viciUser)->first();
        $userId = $user?->id;

        $processed = match ($event) {
            'call_answered' => $this->handleCallAnswered($user, $leadId, $message),
            'call_dead', 'agent_hangup' => $this->handleCallEnded($user, $event),
            'call_dialed' => $this->handleCallDialed($user, $message),
            'state_ready' => $this->handleStateChange($userId, $viciUser, 'ready', $message),
            'state_paused' => $this->handleStateChange($userId, $viciUser, 'paused', $message),
            'logged_in' => $this->handleLogin($userId, $viciUser, $message),
            'logged_out', 'logged_out_complete' => $this->handleLogout($userId, $viciUser, $event),
            'dispo_set' => $this->handleDispoSet($user, $leadId, $message),
            default => $this->handleGenericEvent($userId, $event, $message),
        };

        return response()->json([
            'received' => true,
            'processed' => $processed,
            'event' => $event,
        ]);
    }

    private function handleCallAnswered(?User $user, mixed $leadId, string $message): bool
    {
        if (! $user) {
            return false;
        }

        $session = CallSession::where('user_id', $user->id)->active()->orderByDesc('dialed_at')->first();
        if ($session && ! $session->isTerminal()) {
            $this->callStateService->transition($session, CallSession::STATUS_IN_CALL);
        }

        $phoneNumber = $message ?: ($session?->phone_number ?? '');
        if ($phoneNumber || $leadId) {
            event(new InboundCallReceived(
                userId: $user->id,
                phoneNumber: $phoneNumber,
                leadId: $leadId ? (int) $leadId : null,
            ));
        }

        return true;
    }

    private function handleCallEnded(?User $user, string $event): bool
    {
        if (! $user) {
            return false;
        }

        $session = CallSession::where('user_id', $user->id)->active()->orderByDesc('dialed_at')->first();
        if ($session && ! $session->isTerminal()) {
            $endReason = $event === 'call_dead' ? 'customer_hangup' : 'agent_hangup_vici';
            $this->callStateService->recordHangup($session, ['end_reason' => $endReason]);
        }

        return true;
    }

    private function handleCallDialed(?User $user, string $message): bool
    {
        if (! $user) {
            return false;
        }

        $this->broadcastAgentEvent($user->id, 'call_dialed', $message);

        return true;
    }

    private function handleStateChange(?int $userId, string $viciUser, string $status, string $message): bool
    {
        if ($userId) {
            VicidialAgentSession::where('user_id', $userId)
                ->latest()
                ->first()
                ?->update(['session_status' => $status]);
        }

        $this->broadcastAgentEvent($userId, 'state_'.$status, $message);

        return true;
    }

    private function handleLogin(?int $userId, string $viciUser, string $message): bool
    {
        $this->broadcastAgentEvent($userId, 'logged_in', $message);

        return true;
    }

    private function handleLogout(?int $userId, string $viciUser, string $event): bool
    {
        if ($userId) {
            VicidialAgentSession::where('user_id', $userId)
                ->latest()
                ->first()
                ?->update(['session_status' => 'logged_out']);
        }

        $this->broadcastAgentEvent($userId, $event, '');

        return true;
    }

    private function handleDispoSet(?User $user, mixed $leadId, string $message): bool
    {
        $this->broadcastAgentEvent($user?->id, 'dispo_set', $message, [
            'lead_id' => $leadId,
            'disposition_code' => $message,
        ]);

        return true;
    }

    private function handleGenericEvent(?int $userId, string $event, string $message): bool
    {
        $this->broadcastAgentEvent($userId, $event, $message);

        return true;
    }

    private function broadcastAgentEvent(?int $userId, string $event, string $message, array $extra = []): void
    {
        if (! $userId) {
            return;
        }

        event(new VicidialAgentEvent(
            userId: $userId,
            event: $event,
            message: $message,
            extra: $extra,
        ));
    }
}
