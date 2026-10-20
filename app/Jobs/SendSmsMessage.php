<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Models\SmsChannel;
use App\Models\SmsGateway;
use App\Models\SmsMessage;
use App\Services\Sms\GoipHttpTransport;
use App\Services\Sms\SmsCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(public int $messageId)
    {
        $this->onConnection(config('sms.queue_connection'));
        $this->onQueue(config('sms.queue'));
    }

    public function handle(SmsCampaignService $campaigns, GoipHttpTransport $transport): void
    {
        \Illuminate\Support\Facades\Cache::put('sms:worker-heartbeat', now()->toIso8601String(), 300);
        $candidate = SmsMessage::find($this->messageId);
        if (! $candidate || $candidate->status !== 'queued' || ! $candidate->sms_campaign_id) {
            return;
        }
        $claim = DB::transaction(function () use ($candidate, $campaigns, $transport): ?array {
            // Stable lock order: gateway, campaign, channel, message.
            $gatewayId = SmsCampaign::whereKey($candidate->sms_campaign_id)->value('sms_gateway_id');
            $gateway = SmsGateway::lockForUpdate()->find($gatewayId);
            $campaign = SmsCampaign::lockForUpdate()->find($candidate->sms_campaign_id);
            if (! $gateway || ! $campaign || ! in_array($campaign->status, ['scheduled', 'queued', 'running'], true)
                || $campaign->scheduled_at?->isFuture()) {
                return null;
            }
            if (! $campaign->creator?->isSuperAdmin()) {
                $campaign->update(['status' => 'failed']);
                $campaign->messages()->where('status', 'queued')->update(['status' => 'canceled', 'reason' => 'creator_not_authorized']);

                return null;
            }
            $hour = now('Asia/Manila')->hour;
            $quiet = $campaign->quiet_start < $campaign->quiet_end
                ? $hour >= $campaign->quiet_start && $hour < $campaign->quiet_end
                : $hour >= $campaign->quiet_start || $hour < $campaign->quiet_end;
            if ($quiet || $gateway->reserved_until?->isFuture()) {
                return null;
            }

            try {
                $transport->assertReady($gateway);
            } catch (Throwable) {
                return null;
            }
            $channel = $gateway->channels()->whereIn('id', $campaign->channel_ids)->where('enabled', true)
                ->where(fn ($query) => $query->whereNull('next_send_at')->orWhere('next_send_at', '<=', now()))
                ->orderBy('id')->lockForUpdate()->get()->first(function (SmsChannel $channel): bool {
                    return $channel->usage_date?->toDateString() !== now('Asia/Manila')->toDateString()
                        || $channel->used_today < $channel->daily_limit;
                });
            if (! $channel) {
                return null;
            }
            $message = SmsMessage::lockForUpdate()->find($candidate->id);
            if ($message->status !== 'queued') {
                return null;
            }
            $window = $campaign->scheduled_at ?? $campaign->confirmed_at ?? $message->created_at;
            if ($window->lt(now()->subDays(3))) {
                $message->update(['status' => 'canceled', 'reason' => 'message_expired']);

                return null;
            }
            if (! $campaigns->eligible($message, $campaign)) {
                $message->update(['status' => 'canceled', 'reason' => 'consent_or_suppression_changed']);

                return null;
            }
            // Reservation is the submission boundary. A crash from here on is unknown.
            $message->update(['status' => 'processing', 'sms_channel_id' => $channel->id, 'processing_at' => now()]);
            $gateway->update(['reserved_until' => now()->addSeconds(120)]);
            $used = $channel->usage_date?->toDateString() === now('Asia/Manila')->toDateString() ? $channel->used_today : 0;
            $channel->update(['usage_date' => now('Asia/Manila')->toDateString(), 'used_today' => $used + 1, 'next_send_at' => now()->addSeconds($channel->interval_seconds)]);
            $campaign->update(['status' => 'running']);
            $channel->setRelation('gateway', $gateway);

            return [$message, $channel];
        });
        if (! $claim) {
            // Scheduler retries only records still queued; this job never retries a submission.
            SmsMessage::whereKey($candidate->id)->where('status', 'queued')->update(['enqueued_at' => null]);

            return;
        }
        [$message, $channel] = $claim;

        try {
            $result = $transport->send($channel, $message->phone, $message->body);
        } catch (Throwable) {
            $result = ['status' => 'unknown', 'reason' => 'submission_interrupted', 'gateway_message_id' => null];
        }
        DB::transaction(function () use ($message, $channel, $result): void {
            $gateway = SmsGateway::lockForUpdate()->findOrFail($channel->sms_gateway_id);
            $channel = SmsChannel::lockForUpdate()->findOrFail($channel->id);
            $updated = SmsMessage::whereKey($message->id)->where('status', 'processing')->update([
                ...$result, 'submitted_at' => $result['status'] === 'submitted' ? now() : null,
            ]);
            if ($updated) {
                \App\Models\SmsGatewayEvent::firstOrCreate([
                    'sms_channel_id' => $channel->id, 'event_key' => 'http:'.$message->idempotency_key,
                ], ['kind' => 'http_'.$result['status'], 'sms_message_id' => $message->id, 'payload_hash' => hash('sha256', json_encode($result))]);
            }
            if ($updated && $result['status'] === 'submitted') {
                $gateway->last_contact_at = now();
                $channel->last_contact_at = now();
            } else {
                // Require operator review after ambiguity or authentication failure.
                $channel->enabled = false;
            }
            $channel->save();
            $gateway->reserved_until = null;
            $gateway->save();
        });
    }
}
