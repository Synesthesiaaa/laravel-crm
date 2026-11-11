<?php

namespace App\Services\Email;

use App\Jobs\NotifyEmailCampaignOwner;
use App\Models\EmailCampaign;
use Illuminate\Support\Facades\DB;

class CampaignNotifications
{
    public function record(EmailCampaign $campaign, string $event): void
    {
        $created = DB::table('email_campaign_notifications')->insertOrIgnore([
            'email_campaign_id' => $campaign->id, 'event_key' => $event, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($created) {
            NotifyEmailCampaignOwner::dispatch($campaign->id, $event)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))->afterCommit();
        }
    }
}
