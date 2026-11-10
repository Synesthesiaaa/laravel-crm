<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotifyEmailCampaignOwner implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $campaignId, public string $event) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $record = DB::table('email_campaign_notifications')->where('email_campaign_id', $this->campaignId)->where('event_key', $this->event)->lockForUpdate()->first();
            if (! $record || $record->sent_at) {
                return;
            }
            $campaign = EmailCampaign::find($this->campaignId);
            if ($campaign && User::whereKey($campaign->created_by_user_id)->exists()) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'type' => self::class,
                    'notifiable_type' => User::class, 'notifiable_id' => $campaign->created_by_user_id,
                    'data' => json_encode([
                        'source' => 'Email campaigns', 'title' => str_starts_with($this->event, 'stalled') ? 'Email campaign needs attention' : 'Email campaign '.str_replace('_', ' ', $campaign->status),
                        'message' => $campaign->name.': '.$campaign->sent_count.' accepted; '.$campaign->failed_count.' need review. Acceptance does not confirm delivery.',
                        'url' => route('admin.email-management.show', $campaign), 'campaign_id' => $campaign->id,
                        'type' => $campaign->status === 'completed' ? 'success' : 'warning',
                    ], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('email_campaign_notifications')->where('id', $record->id)->update(['sent_at' => now(), 'updated_at' => now()]);
        });
    }
}
