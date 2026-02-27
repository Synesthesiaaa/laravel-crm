<?php

namespace App\Http\Controllers\Api;

use App\Events\TelephonyEventLogged;
use App\Http\Controllers\Controller;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\CallUuidMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook endpoint for Asterisk AMI events (hangup, channel state, CDR).
 * Idempotent: safe to process duplicate events.
 * Uses CallUuidMappingService for linkedid/channel/extension correlation.
 */
class AmiWebhookController extends Controller
{
    public function __construct(
        protected CallStateService $callStateService,
        protected CallUuidMappingService $mapping,
        protected TelephonyLogger $telephonyLogger
    ) {}

    /**
     * Handle incoming AMI event (POST). Expects JSON payload with event type and identifiers.
     * If ASTERISK_AMI_WEBHOOK_SECRET is set, require X-Webhook-Secret header.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('asterisk.webhook_secret');
        if ($secret !== '' && $request->header('X-Webhook-Secret') !== $secret) {
            $this->telephonyLogger->warning('AmiWebhookController', 'AMI webhook rejected: invalid or missing secret');

            return response()->json(['received' => false, 'error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? $payload['Event'] ?? null;
        $linkedid = $payload['linkedid'] ?? $payload['Linkedid'] ?? $payload['Uniqueid'] ?? null;
        $channel = $payload['channel'] ?? $payload['Channel'] ?? null;

        if (empty($event)) {
            return response()->json(['received' => true, 'processed' => false, 'reason' => 'missing event']);
        }

        $this->telephonyLogger->event('AmiWebhookController', (string) $event, 'AMI webhook received', [
            'linkedid' => $linkedid,
            'channel' => $channel,
        ]);
        event(new TelephonyEventLogged(
            (string) $event,
            'info',
            'AMI webhook received',
            ['linkedid' => $linkedid, 'channel' => $channel]
        ));

        $processed = false;
        $reason = 'event not handled';

        if (in_array($event, ['Hangup', 'HangupRequest', 'SoftHangupRequest'], true)) {
            $processed = $this->handleHangup($linkedid, $channel, $payload);
            $reason = $processed ? 'hangup processed' : 'no matching session';
        } elseif ($event === 'Bridge' || $event === 'BridgeEnter') {
            // Bridge event: both legs are connected – call is established
            $processed = $this->handleBridge($linkedid, $channel, $payload);
            $reason = $processed ? 'bridge/established processed' : 'no matching session for bridge';
        } elseif ($event === 'DialEnd') {
            $processed = $this->handleDialEnd($linkedid, $channel, $payload);
            $reason = $processed ? 'dialend processed' : 'no matching session for dialend';
        }

        return response()->json([
            'received' => true,
            'processed' => $processed,
            'reason' => $reason,
        ]);
    }

    /**
     * Bridge event: both legs connected → transition to in_call (established).
     * Handles both Asterisk 13 "Bridge" and Asterisk 16+ "BridgeEnter".
     */
    protected function handleBridge(?string $linkedid, ?string $channel, array $payload): bool
    {
        $session = $this->mapping->findSessionForHangup($linkedid, $channel, $payload);

        if (! $session) {
            $this->telephonyLogger->debug('AmiWebhookController', 'No matching session for Bridge event', [
                'linkedid' => $linkedid,
                'channel'  => $channel,
            ]);
            return false;
        }

        if ($session->isTerminal()) {
            return false;
        }

        $this->mapping->attachAsteriskIdentifiers($session, $linkedid, $channel);

        // Transition ringing/answered → in_call
        $result = $this->callStateService->transition($session, \App\Models\CallSession::STATUS_IN_CALL, [
            'linkedid' => $linkedid,
            'channel'  => $channel,
            'metadata' => ['ami_payload' => $payload],
        ]);

        return $result->success;
    }

    /**
     * DialEnd event: ANSWER means call was picked up by remote (GoIP/GSM answered).
     * Other dial statuses (NOANSWER, BUSY, CANCEL, CONGESTION) map to failure.
     */
    protected function handleDialEnd(?string $linkedid, ?string $channel, array $payload): bool
    {
        $dialStatus = $payload['dialstatus'] ?? $payload['DialStatus'] ?? null;

        if (! $dialStatus) {
            return false;
        }

        $session = $this->mapping->findSessionForHangup($linkedid, $channel, $payload);

        if (! $session || $session->isTerminal()) {
            return false;
        }

        $this->mapping->attachAsteriskIdentifiers($session, $linkedid, $channel);

        if ($dialStatus === 'ANSWER') {
            // Remote (GSM) answered → establish the call
            $result = $this->callStateService->transition(
                $session,
                \App\Models\CallSession::STATUS_IN_CALL,
                ['linkedid' => $linkedid, 'channel' => $channel, 'metadata' => ['ami_payload' => $payload]]
            );
        } else {
            // NOANSWER, BUSY, CANCEL, CONGESTION, etc. → fail
            $endReason = match ($dialStatus) {
                'NOANSWER'   => 'no_answer',
                'BUSY'       => 'busy',
                'CANCEL'     => 'cancelled',
                'CONGESTION' => 'congestion',
                default      => 'dial_failed_' . strtolower($dialStatus),
            };
            $result = $this->callStateService->transition(
                $session,
                \App\Models\CallSession::STATUS_FAILED,
                ['end_reason' => $endReason, 'metadata' => ['ami_payload' => $payload]],
                true
            );
        }

        return $result->success;
    }

    /**
     * Idempotent hangup handling: find session via mapping service (linkedid, channel, extension fallback).
     */
    protected function handleHangup(?string $linkedid, ?string $channel, array $payload): bool
    {
        $session = $this->mapping->findSessionForHangup($linkedid, $channel, $payload);

        if (! $session) {
            $this->mapping->logUnmatched('Hangup', $linkedid, $channel, $payload);
            $this->telephonyLogger->debug('AmiWebhookController', 'No matching session for hangup', [
                'linkedid' => $linkedid,
                'channel' => $channel,
            ]);
            event(new TelephonyEventLogged(
                'Hangup',
                'warning',
                'AMI hangup unmatched to any active session',
                ['linkedid' => $linkedid, 'channel' => $channel]
            ));

            return false;
        }

        $this->mapping->attachAsteriskIdentifiers($session, $linkedid, $channel);

        $result = $this->callStateService->recordHangup($session, [
            'end_reason' => 'ami_hangup',
            'linkedid' => $linkedid,
            'channel' => $channel,
            'metadata' => ['ami_payload' => $payload],
        ]);

        return $result->success;
    }
}
