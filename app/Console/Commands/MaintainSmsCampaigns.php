<?php

namespace App\Console\Commands;

use App\Jobs\SendSmsMessage;
use App\Models\SmsCampaign;
use App\Models\SmsChannel;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MaintainSmsCampaigns extends Command
{
    protected $signature = 'sms:maintain';

    protected $description = 'Recover interrupted SMS submissions and enqueue bounded pending work';

    public function handle(): int
    {
        Cache::put('sms:scheduler-heartbeat', now()->toIso8601String(), 300);
        SmsMessage::where('status', 'processing')->where('processing_at', '<', now()->subMinutes(5))->chunkById(100, function ($messages): void {
            foreach ($messages as $message) {
                DB::transaction(function () use ($message): void {
                    if (SmsMessage::whereKey($message->id)->where('status', 'processing')->update(['status' => 'unknown', 'reason' => 'worker_interrupted'])) {
                        SmsChannel::whereKey($message->sms_channel_id)->update(['enabled' => false]);
                    }
                });
            }
        });
        SmsCampaign::whereIn('status', ['scheduled', 'queued', 'running'])->chunkById(100, function ($campaigns): void {
            foreach ($campaigns as $campaign) {
                if (! $campaign->messages()->whereIn('status', ['queued', 'processing'])->exists()) {
                    SmsCampaign::whereKey($campaign->id)->whereIn('status', ['scheduled', 'queued', 'running'])->update(['status' => 'completed']);
                }
            }
        });
        $connection = config('sms.queue_connection');
        if (! in_array(config("queue.connections.{$connection}.driver"), ['database', 'redis'], true)) {
            $this->error('SMS requires a durable database or Redis queue.');

            return self::FAILURE;
        }
        SmsMessage::where('status', 'queued')->whereHas('campaign', fn ($query) => $query->whereIn('status', ['queued', 'scheduled', 'running'])
            ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now())))
            ->where(fn ($query) => $query->whereNull('enqueued_at')->orWhere('enqueued_at', '<', now()->subMinutes(10)))
            ->orderBy('id')->limit(100)->get()->each(function (SmsMessage $message): void {
                $claimed = SmsMessage::whereKey($message->id)->where('status', 'queued')
                    ->where(fn ($query) => $query->whereNull('enqueued_at')->orWhere('enqueued_at', '<', now()->subMinutes(10)))
                    ->update(['enqueued_at' => now()]);
                if ($claimed) {
                    SendSmsMessage::dispatch($message->id)->afterCommit();
                }
            });
        $this->info('SMS maintenance complete. Unknown submissions were not retried.');

        return self::SUCCESS;
    }
}
