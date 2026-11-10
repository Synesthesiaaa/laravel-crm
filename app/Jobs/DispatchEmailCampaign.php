<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class DispatchEmailCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $campaignId, public int $generation) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        $more = DB::transaction(function (): bool {
            $campaign = EmailCampaign::lockForUpdate()->find($this->campaignId);
            $intent = DB::table('email_dispatch_intents')->where('email_campaign_id', $this->campaignId)->where('generation', $this->generation)->lockForUpdate()->first();
            if (! $intent || $intent->status !== 'pending') {
                return false;
            }
            if (! $campaign || $campaign->generation !== $this->generation || ! in_array($campaign->status, ['queued', 'processing', 'sending'], true)) {
                DB::table('email_dispatch_intents')->where('id', $intent->id)->update(['status' => 'obsolete', 'heartbeat_at' => now(), 'updated_at' => now()]);

                return false;
            }
            $ids = $campaign->recipients()->where('status', 'pending')->where('id', '>', $intent->cursor)->orderBy('id')->limit(250)->pluck('id');
            if ($ids->isEmpty()) {
                DB::table('email_dispatch_intents')->where('id', $intent->id)->update(['status' => 'completed', 'heartbeat_at' => now(), 'updated_at' => now()]);
                app(\App\Services\EmailCampaignRecipientTransitionService::class)->reconcile($campaign->id);

                return false;
            }
            $jobs = [];
            foreach ($ids as $index => $id) {
                $jobs[] = (new SendManagedEmailRecipient($campaign->id, $id, $this->generation))
                    ->delay($campaign->started_at->copy()->addSeconds((int) floor(($intent->dispatched_count + $index) / max(1, $campaign->batch_size)) * $campaign->delay_seconds));
            }
            // Batch registration and the durable cursor share the database transaction.
            // Cross-connection queue gaps are recovered from pending recipient rows; claims prevent replay sends.
            $batch = Bus::batch($jobs)->name('Email campaign '.$campaign->id.' generation '.$this->generation)
                ->allowFailures()->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))->dispatch();
            DB::table('email_dispatch_intents')->where('id', $intent->id)->update(['cursor' => $ids->last(), 'dispatched_count' => $intent->dispatched_count + $ids->count(), 'batch_id' => $batch->id, 'status' => 'pending', 'heartbeat_at' => now(), 'updated_at' => now()]);

            return true;
        });
        if ($more) {
            self::dispatch($this->campaignId, $this->generation)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))->delay(now()->addSeconds(1));
        }
    }
}
