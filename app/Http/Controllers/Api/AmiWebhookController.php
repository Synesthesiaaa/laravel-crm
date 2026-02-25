<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\CallUuidMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook endpoint for Asterisk AMI events (hangup, channel state, CDR).
 * Idempotent: safe to process duplicate events.
 * Uses CallUuidMappingService for linkedid/channel/extension correlation.
 */
class AmiWebhookController extends Controller
{
    public function __construct(
        protected CallStateService $callStateService,
        protected CallUuidMappingService $mapping
    ) {}

    /**
     * Handle incoming AMI event (POST). Expects JSON payload with event type and identifiers.
     * If ASTERISK_AMI_WEBHOOK_SECRET is set, require X-Webhook-Secret header.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('asterisk.webhook_secret');
        if ($secret !== '' && $request->header('X-Webhook-Secret') !== $secret) {
            Log::channel('telephony')->warning('AMI webhook rejected: invalid or missing secret');

            return response()->json(['received' => false, 'error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? $payload['Event'] ?? null;
        $linkedid = $payload['linkedid'] ?? $payload['Linkedid'] ?? $payload['Uniqueid'] ?? null;
        $channel = $payload['channel'] ?? $payload['Channel'] ?? null;

        if (empty($event)) {
            return response()->json(['received' => true, 'processed' => false, 'reason' => 'missing event']);
        }

        Log::channel('telephony')->debug('AMI webhook received', ['event' => $event, 'linkedid' => $linkedid]);

        $processed = false;
        $reason = 'event not handled';

        if (in_array($event, ['Hangup', 'HangupRequest', 'SoftHangupRequest'], true)) {
            $processed = $this->handleHangup($linkedid, $channel, $payload);
            $reason = $processed ? 'hangup processed' : 'no matching session';
        }

        return response()->json([
            'received' => true,
            'processed' => $processed,
            'reason' => $reason,
        ]);
    }

    /**
     * Idempotent hangup handling: find session via mapping service (linkedid, channel, extension fallback).
     */
    protected function handleHangup(?string $linkedid, ?string $channel, array $payload): bool
    {
        $session = $this->mapping->findSessionForHangup($linkedid, $channel, $payload);

        if (! $session) {
            $this->mapping->logUnmatched('Hangup', $linkedid, $channel, $payload);
            Log::channel('telephony')->debug('AMI webhook: No matching session for hangup', [
                'linkedid' => $linkedid,
                'channel' => $channel,
            ]);

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
