<?php

namespace App\Console\Commands;

use App\Jobs\DispatchEmailCampaign;
use App\Jobs\NotifyEmailCampaignOwner;
use App\Models\EmailCampaign;
use App\Services\Email\CampaignLifecycle;
use App\Services\Email\CampaignNotifications;
use App\Services\Email\ManagedEmailDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchDueEmailCampaigns extends Command
{
    protected $signature = 'email-campaigns:dispatch-due {--recover : Recover pending dispatch and recipients after worker interruption}';

    protected $description = 'Dispatch due approved campaigns and diagnose/recover durable email work without resending uncertain attempts';

    public function handle(CampaignLifecycle $lifecycle): int
    {
        Cache::put('email-scheduler-heartbeat', now()->toIso8601String(), 180);
        app(\App\Services\Email\ProtectedEmailImportService::class)->maintain();
        app(\App\Services\Email\AudienceService::class)->cleanupExpired();
        foreach (EmailCampaign::where('status', 'draft')->where('approval_state', 'queued')->where('approval_requested_at', '<', now()->subMinutes(2))->lazyById(100) as $approval) {
            $approval->forceFill(['approval_error' => 'Approval preparation has not started. Check the supervised preparation worker and queue.'])->save();
            app(CampaignNotifications::class)->record($approval, 'stalled_approval:'.$approval->generation);
            \App\Jobs\ApproveEmailCampaign::dispatch($approval->id, $approval->generation);
        }
        foreach (\App\Models\EmailAudienceImport::whereIn('status', ['queued', 'processing', 'applying'])->where(fn ($q) => $q->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', now()->subMinutes(3)))->lazyById(100, 'id') as $import) {
            \App\Jobs\PrepareEmailAudience::dispatch($import->id);
        }
        foreach (EmailCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('id')->lazyById(100) as $campaign) {
            try {
                $lifecycle->due($campaign->id);
            } catch (ValidationException) {
                $campaign->forceFill(['pause_reason' => 'Scheduled dispatch blocked. Check worker, SMTP, live policy and approved document integrity.'])->save();
                app(CampaignNotifications::class)->record($campaign, 'stalled:'.$campaign->generation);
            }
        }
        foreach (EmailCampaign::where('management_version', '>=', 3)->whereIn('status', ['queued', 'processing'])->where('progress_at', '<', now()->subMinutes(5))->lazyById(100) as $campaign) {
            app(CampaignNotifications::class)->record($campaign, 'stalled:'.$campaign->generation);
        }
        foreach (DB::table('email_campaign_recipients')->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(3))->orderBy('id')->lazyById(100) as $recipient) {
            if (EmailCampaign::whereKey($recipient->email_campaign_id)->where('management_version', '>=', 3)->exists()) {
                app(ManagedEmailDelivery::class)->interrupted($recipient->email_campaign_id, $recipient->id);
            }
        }
        foreach (DB::table('email_dispatch_intents')->where('status', 'pending')->where(fn ($q) => $q->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', now()->subMinutes(2)))->orderBy('id')->lazyById(100) as $intent) {
            DispatchEmailCampaign::dispatch($intent->email_campaign_id, $intent->generation)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'));
        }
        if ($this->option('recover')) {
            foreach (EmailCampaign::where('management_version', '>=', 3)->whereIn('status', ['queued', 'processing'])->where('progress_at', '<', now()->subMinutes(5))->lazyById(100) as $campaign) {
                $lastEligibility = $campaign->started_at->copy()->addSeconds((int) floor(max(0, $campaign->recipient_count - 1) / max(1, $campaign->batch_size)) * $campaign->delay_seconds)->addMinutes(5);
                if ($lastEligibility->isFuture()) {
                    continue;
                }
                // Cursor replay is safe: the next atomic claim excludes accepted and uncertain rows.
                DB::table('email_dispatch_intents')->where('email_campaign_id', $campaign->id)->where('generation', $campaign->generation)->update(['cursor' => 0, 'dispatched_count' => 0, 'status' => 'pending', 'heartbeat_at' => null]);
                DispatchEmailCampaign::dispatch($campaign->id, $campaign->generation)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'));
            }
        }
        foreach (DB::table('email_campaign_notifications')->whereNull('sent_at')->orderBy('id')->lazyById(100) as $notification) {
            NotifyEmailCampaignOwner::dispatch($notification->email_campaign_id, $notification->event_key)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'));
        }
        $this->info('Due campaigns inspected; durable email work checked. Uncertain submissions were not resent.');

        return self::SUCCESS;
    }
}
