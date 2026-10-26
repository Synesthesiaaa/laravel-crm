<?php

namespace App\Http\Controllers\Api;

use App\Events\InboundCallReceived;
use App\Events\VicidialAgentEvent;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\LeadHydrationService;
use App\Services\Telephony\TelephonyLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives ViciDial Agent Push Events.
 * This is the instant push path when Agent Push Events is configured;
 * `/api/telephony/active-lead` acts as the always-on polling fallback.
 * Configure Agent Push URL in ViciDial System Settings:
 *   get2post.php?HTTPURLTOPOST=http://CRM:8000/api/webhooks/vicidial-events
 *     ?user=--A--user--B--&event=--A--event--B--&message=--A--message--B--
 *     &lead_id=--A--lead_id--B--&counter=--A--counter--B--
 */
class VicidialEventsWebhookController extends Controller
{
    public function __construct(
        protected CallStateService $callStateService,
        protected LeadHydrationService $leadHydrationService,
        protected TelephonyLogger $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('vicidial.events_webhook_secret', '');
        if ($secret === '') {
            $this->logger->warning('VicidialEventsWebhookController', 'ViciDial webhook rejected: secret is not configured');

            return response()->json([
                'received' => false,
                'processed' => false,
                'error' => 'Webhook secret is not configured',
            ], 503);
        }
        if (! hash_equals($secret, (string) $request->header('X-Webhook-Secret', ''))) {
            $this->logger->warning('VicidialEventsWebhookController', 'ViciDial webhook rejected: invalid or missing secret');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $viciUser = $request->input('user', '');
        $event = $request->input('event', '');
        $message = is_scalar($request->input('message')) ? trim((string) $request->input('message')) : '';
        $leadId = $request->input('lead_id');
        $campaign = $this->stringInput($request->input('campaign'));

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
        $callId = $this->eventCallId($user, $request->input('call_id'), $message);

        $processed = match ($event) {
            'call_answered' => $this->handleCallAnswered(
                $user,
                $leadId,
                $callId,
                $campaign,
                $this->stringInput($request->input('phone_number')),
            ),
            'call_dead', 'agent_hangup' => $this->handleCallEnded($user, $event, $leadId, $callId, $campaign),
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

    private function handleCallAnswered(
        ?User $user,
        mixed $leadId,
        ?string $callId,
        ?string $campaign,
        ?string $phoneNumber,
    ): bool {
        if (! $user) {
            return false;
        }

        $session = $this->findCorrelatedCallSession($user, $leadId, $callId, $campaign);
        if ($session) {
            $result = $this->callStateService->markConnected($session);
            if (! $result->success) {
                return false;
            }
        }

        $campaignCode = (string) ($session?->campaign_code ?: $campaign ?: config('vicidial.default_campaign', 'mbsales'));
        $phoneNumber = $session?->phone_number ?: $phoneNumber ?: '';
        $hydrated = null;
        if ($leadId || $phoneNumber) {
            $hydrated = $this->leadHydrationService->hydrate(
                $user,
                $campaignCode,
                $leadId ? (int) $leadId : null,
                $phoneNumber !== '' ? $phoneNumber : null,
            );
        }

        if ($phoneNumber || $leadId) {
            event(new InboundCallReceived(
                userId: $user->id,
                phoneNumber: (string) ($hydrated['phone_number'] ?? $phoneNumber),
                leadId: ($hydrated['lead_id'] ?? null) ? (int) $hydrated['lead_id'] : ($leadId ? (int) $leadId : null),
                clientName: $hydrated['client_name'] ?? null,
                campaignCode: $campaignCode,
                leadData: (array) ($hydrated['capture_data'] ?? []),
            ));
        }

        return $session !== null || (bool) ($leadId || $phoneNumber);
    }

    private function handleCallEnded(
        ?User $user,
        string $event,
        mixed $leadId,
        ?string $callId,
        ?string $campaign,
    ): bool {
        if (! $user || ! $callId) {
            return false;
        }

        $session = $this->findCorrelatedCallSession($user, $leadId, $callId, $campaign);
        if (! $session) {
            return false;
        }

        $endReason = $event === 'call_dead' ? 'customer_hangup' : 'agent_hangup_vici';

        // An exact call ID protects a newer session while preserving out-of-order
        // completion when VICIdial has not delivered its answer event yet.
        return $this->callStateService->transition($session, CallSession::STATUS_COMPLETED, [
            'end_reason' => $endReason,
            'assume_connected' => true,
            'remote_outcome_resolved' => true,
        ])->success;
    }

    private function findCorrelatedCallSession(?User $user, mixed $leadId, ?string $callId, ?string $campaign): ?CallSession
    {
        if (! $user || ! $callId) {
            return null;
        }

        $query = CallSession::query()
            ->where('user_id', $user->id)
            ->where('vicidial_call_id', $callId);

        if ($campaign !== null) {
            $query->where('campaign_code', $campaign);
        }

        if (is_numeric($leadId) && (int) $leadId > 0) {
            $query->where(function ($builder) use ($leadId): void {
                $builder->where('lead_id', (int) $leadId)
                    ->orWhere('vicidial_lead_id', (string) (int) $leadId);
            });
        }

        return $query->first();
    }

    private function eventCallId(?User $user, mixed $requestCallId, string $message): ?string
    {
        $callId = $this->stringInput($requestCallId);
        if ($callId !== null) {
            return $callId;
        }

        if (! $user || $message === '') {
            return null;
        }

        return CallSession::query()
            ->where('user_id', $user->id)
            ->where('vicidial_call_id', $message)
            ->exists()
                ? $message
                : null;
    }

    private function stringInput(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' && strlen($value) <= 255 ? $value : null;
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
