<?php

namespace App\Console\Commands;

use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use Illuminate\Console\Command;

class PruneSmsContent extends Command
{
    protected $signature = 'sms:prune';

    protected $description = 'Remove expired SMS content only under the explicitly enabled retention policy';

    public function handle(): int
    {
        $days = (int) config('sms.retention_days');
        if ($days <= 0) {
            $this->info('SMS content retention is not enabled. No records changed.');

            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $count = SmsMessage::where('created_at', '<', $cutoff)->whereNotIn('status', ['queued', 'processing', 'eligible'])
            ->whereNotNull('body')->update(['body' => null, 'recipient_name' => null]);
        SmsCampaign::whereIn('status', ['completed', 'failed', 'canceled'])->where('updated_at', '<', $cutoff)
            ->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    $campaign->update(['body' => '[Content removed under the approved retention policy]', 'description' => null]);
                }
            });
        // Preserve numbers, consent references, states and UDP deduplication keys.
        activity('sms')->withProperties(['retention_days' => $days, 'message_bodies_removed' => $count])->log('SMS content retention applied');
        $this->info("Removed {$count} expired message bodies. Idempotency and consent records preserved.");

        return self::SUCCESS;
    }
}
