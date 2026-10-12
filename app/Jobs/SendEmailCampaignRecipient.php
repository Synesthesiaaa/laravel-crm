<?php

namespace App\Jobs;

use App\Services\EmailCampaignDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailCampaignRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $campaignId, public int $recipientId) {}

    public function handle(EmailCampaignDeliveryService $delivery): void
    {
        $delivery->send($this->campaignId, $this->recipientId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(EmailCampaignDeliveryService::class)->interrupted($this->campaignId, [$this->recipientId]);
    }
}
