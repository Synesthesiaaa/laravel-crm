<?php

namespace App\Services;

class EmailCampaignSendingPolicy
{
    public function allowsLive(): bool
    {
        return (bool) config('email_campaigns.live_enabled')
            && ! app()->environment('testing')
            && (! app()->environment('local') || (bool) config('email_campaigns.allow_local'));
    }
}
