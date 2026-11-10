<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailProviderEvent;
use App\Models\EmailProviderEvent;
use App\Models\EmailTrackingToken;
use Illuminate\Console\Command;

class MaintainEmailFeedback extends Command
{
    protected $signature = 'email-feedback:maintain';

    protected $description = 'Recover unprocessed email provider receipts and expire minimal feedback/tracking records';

    public function handle(): int
    {
        $recovered = 0;
        EmailProviderEvent::whereNull('processed_at')->where('created_at', '<=', now()->subMinutes(2))->orderBy('id')->chunkById(100, function ($events) use (&$recovered): void {
            foreach ($events as $event) {
                ProcessEmailProviderEvent::dispatch($event->id)
                    ->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'));
                $recovered++;
            }
        });
        $tokens = EmailTrackingToken::whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
        $days = max(7, (int) config('email_campaigns.event_retention_days', 90));
        $events = EmailProviderEvent::whereNotNull('processed_at')->where('processed_at', '<', now()->subDays($days))->delete();
        $this->info("Recovered {$recovered} provider receipts; expired {$tokens} tracking tokens and {$events} processed events. Suppressions and unsubscribe tokens were retained.");

        return self::SUCCESS;
    }
}
