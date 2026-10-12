<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Services\EmailCampaignDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $campaignId, public int $offset, public int $size) {}

    public function handle(EmailCampaignDeliveryService $delivery): void
    {
        $campaign = EmailCampaign::find($this->campaignId);
        if (! $campaign || ! in_array($campaign->status, ['queued', 'sending'], true)) {
            return;
        }
        $ids = $campaign->recipients()->orderBy('id')->offset($this->offset)->limit($this->size)->pluck('id');
        foreach ($ids as $id) {
            if ($campaign->delivery_snapshot) {
                // New batches distribute ID-only jobs; each SMTP attempt has its own bounded timeout.
                SendEmailCampaignRecipient::dispatch($campaign->id, $id)
                    ->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'));
            } else {
                // Compatibility for campaigns already queued by the previous worker.
                $delivery->send($campaign->id, $id);
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $campaign = EmailCampaign::find($this->campaignId);
        if ($campaign) {
            app(EmailCampaignDeliveryService::class)->interrupted($campaign->id,
                $campaign->recipients()->orderBy('id')->offset($this->offset)->limit($this->size)->pluck('id')->all());
        }
    }
}
