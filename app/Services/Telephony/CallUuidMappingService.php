<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\UnmatchedAmiEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Maps Asterisk AMI identifiers (linkedid, channel, extension) to Laravel CallSession.
 * Provides fallback matching when linkedid is not yet stored on the session.
 */
class CallUuidMappingService
{
    /**
     * Extract SIP/PJSIP extension from channel name.
     * Formats: SIP/1001-00000001, PJSIP/agent1-00000001, Local/1001@context-00000001
     */
    public function extractExtensionFromChannel(?string $channel): ?string
    {
        if (empty($channel)) {
            return null;
        }
        if (preg_match('#(?:SIP|PJSIP|Local)/([^/-]+)#i', $channel, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Find user by extension. Uses extension column or falls back to vici_user.
     */
    public function findUserByExtension(string $extension): ?User
    {
        $user = User::where('extension', $extension)->first();
        if ($user) {
            return $user;
        }

        return User::where('vici_user', $extension)->first();
    }

    /**
     * Find call session for hangup event. Tries linkedid, channel, then extension fallback.
     *
     * @return CallSession|null
     */
    public function findSessionForHangup(?string $linkedid, ?string $channel, array $payload = []): ?CallSession
    {
        if ($linkedid) {
            $session = CallSession::where('linkedid', $linkedid)->active()->first();
            if ($session) {
                return $session;
            }
        }

        if ($channel) {
            $session = CallSession::where('channel', $channel)->active()->first();
            if ($session) {
                return $session;
            }
        }

        return $this->findSessionByExtensionFallback($channel, $payload);
    }

    /**
     * Fallback: extract extension, find user, get their active session.
     */
    protected function findSessionByExtensionFallback(?string $channel, array $payload): ?CallSession
    {
        $extension = $this->extractExtensionFromChannel($channel)
            ?? ($payload['exten'] ?? null)
            ?? ($payload['Exten'] ?? null);

        if (empty($extension)) {
            return null;
        }

        $user = $this->findUserByExtension($extension);
        if (! $user) {
            Log::channel('telephony')->debug('CallUuidMapping: No user for extension', [
                'extension' => $extension,
                'channel' => $channel,
            ]);

            return null;
        }

        $session = CallSession::where('user_id', $user->id)->active()->orderByDesc('dialed_at')->first();
        if ($session) {
            Log::channel('telephony')->info('CallUuidMapping: Matched session by extension fallback', [
                'session_id' => $session->id,
                'extension' => $extension,
                'user_id' => $user->id,
            ]);
        }

        return $session;
    }

    /**
     * Store linkedid/channel on session for future event correlation.
     */
    public function attachAsteriskIdentifiers(CallSession $session, ?string $linkedid = null, ?string $channel = null): void
    {
        $updated = false;
        if ($linkedid && empty($session->linkedid)) {
            $session->linkedid = $linkedid;
            $updated = true;
        }
        if ($channel && empty($session->channel)) {
            $session->channel = $channel;
            $updated = true;
        }
        if ($updated) {
            $session->save();
        }
    }

    /**
     * Log an unmatched AMI event for later reconciliation.
     */
    public function logUnmatched(string $event, ?string $linkedid, ?string $channel, array $payload): UnmatchedAmiEvent
    {
        $extension = $this->extractExtensionFromChannel($channel);

        return UnmatchedAmiEvent::create([
            'event' => $event,
            'linkedid' => $linkedid,
            'channel' => $channel,
            'extracted_extension' => $extension,
            'payload' => $payload,
            'received_at' => now(),
        ]);
    }
}
