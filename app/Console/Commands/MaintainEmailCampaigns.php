<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailCampaignRecipient;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDocument;
use App\Models\EmailCampaignRecipient;
use App\Services\EmailCampaignDeliveryService;
use App\Services\EmailCampaignRecipientTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MaintainEmailCampaigns extends Command
{
    protected $signature = 'email-campaigns:maintain {--recover : Redispatch only pending recipients of frozen campaigns}';

    protected $description = 'Purge expired documents and reconcile interrupted deliveries without resending uncertain messages';

    public function handle(EmailCampaignDeliveryService $delivery, EmailCampaignRecipientTransitionService $transitions): int
    {
        $purged = 0;
        $disk = Storage::disk('email_documents');
        foreach ($disk->allFiles() as $key) {
            if ($disk->lastModified($key) < time() - 3600 && ! EmailCampaignDocument::where('storage_key', $key)->exists()) {
                $disk->delete($key);
            }
        }
        foreach (EmailCampaignDocument::whereNull('purged_at')->where('expires_at', '<=', now())->cursor() as $document) {
            // Do not remove bytes while a recipient is inside the transport boundary.
            if (EmailCampaignRecipient::where('email_campaign_id', $document->email_campaign_id)->where('status', 'sending')->exists()) {
                continue;
            }
            Storage::disk('email_documents')->delete($document->storage_key);
            $document->update(['purged_at' => now(), 'verification_status' => 'purged']);
            Log::info('email_campaign.purge', ['campaign_id' => $document->email_campaign_id, 'document_id' => $document->id]);
            $purged++;
        }
        $ids = EmailCampaignRecipient::where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(3))->pluck('email_campaign_id')->unique();
        foreach ($ids as $id) {
            $stale = EmailCampaignRecipient::where('email_campaign_id', $id)->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(3))->pluck('id');
            $delivery->interrupted($id, $stale->all());
        }
        EmailCampaign::query()->whereIn('status', ['queued', 'sending', 'needs_attention'])->orderBy('id')->chunkById(100, function ($campaigns) use ($transitions): void {
            foreach ($campaigns as $campaign) {
                $transitions->reconcile($campaign->id);
            }
        });
        $recovered = 0;
        if ($this->option('recover')) {
            foreach (EmailCampaign::whereIn('status', ['queued', 'sending'])->whereNotNull('manifest_hash')->cursor() as $campaign) {
                foreach ($campaign->recipients()->where('status', 'pending')->orderBy('id')->cursor() as $index => $recipient) {
                    SendEmailCampaignRecipient::dispatch($campaign->id, $recipient->id)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))
                        ->delay(now()->addSeconds((int) floor($index / $campaign->batch_size) * $campaign->delay_seconds));
                    $recovered++;
                }
            }
        }
        // Remove abandoned extraction copies; random UUID directories only within the private import root.
        $root = storage_path('app/private/email-imports');
        foreach (glob($root.'/*') ?: [] as $dir) {
            if (is_dir($dir) && ! is_link($dir) && preg_match('/^[0-9a-f-]{36}$/', basename($dir)) && filemtime($dir) < time() - 3600) {
                foreach (glob($dir.'/*') ?: [] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                @rmdir($dir);
            }
        }
        $this->info("Purged {$purged} documents; recovered {$recovered} pending deliveries. Uncertain deliveries were not resent.");

        return self::SUCCESS;
    }
}
