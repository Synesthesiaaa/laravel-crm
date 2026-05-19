<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\User;
use App\Models\VicidialAgentSession;

final class AgentCampaignResolver
{
    public static function resolveForUser(User $user, ?CallSession $session): string
    {
        if ($session?->campaign_code) {
            return (string) $session->campaign_code;
        }

        $vicidialSession = VicidialAgentSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('campaign_code')
            ->where('campaign_code', '!=', '')
            ->where('session_status', '!=', 'logged_out')
            ->orderByDesc('logged_in_at')
            ->orderByDesc('last_synced_at')
            ->first();

        if ($vicidialSession?->campaign_code) {
            return (string) $vicidialSession->campaign_code;
        }

        if (! empty($user->default_campaign)) {
            return (string) $user->default_campaign;
        }

        return (string) config('vicidial.default_campaign', 'mbsales');
    }
}
