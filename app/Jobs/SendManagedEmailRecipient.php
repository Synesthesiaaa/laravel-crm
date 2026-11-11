<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Services\Email\ManagedEmailDelivery;
use App\Services\Email\ProviderRateLimiter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendManagedEmailRecipient implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $failOnTimeout = true;

    public int $expiresAt;

    public function __construct(public int $campaignId, public int $recipientId, public int $generation)
    {
        $this->expiresAt = now()->addDay()->timestamp;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return \Carbon\CarbonImmutable::createFromTimestamp($this->expiresAt);
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(ManagedEmailDelivery $delivery, ProviderRateLimiter $limiter): void
    {
        $campaign = EmailCampaign::find($this->campaignId);
        if (! $campaign || $campaign->generation !== $this->generation || ! in_array($campaign->status, ['queued', 'processing', 'sending'], true)
            || $this->batch()?->cancelled()) {
            return;
        }
        if ($delay = $limiter->reserve($campaign->delivery_snapshot ?? [])) {
            $this->release($delay);

            return;
        }
        $delivery->send($this->campaignId, $this->recipientId, $this->generation);
    }

    public function failed(?\Throwable $exception): void
    {
        app(ManagedEmailDelivery::class)->interrupted($this->campaignId, $this->recipientId, $this->generation);
    }
}
