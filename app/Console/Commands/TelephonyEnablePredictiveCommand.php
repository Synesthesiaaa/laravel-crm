<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

class TelephonyEnablePredictiveCommand extends Command
{
    protected $signature = 'telephony:enable-predictive
        {campaign : Campaign code}
        {--delay=3 : Predictive delay in seconds}
        {--max-attempts=3 : Max attempts per lead}';

    protected $description = 'Enable predictive dialing settings for a campaign';

    public function handle(): int
    {
        $code = trim((string) $this->argument('campaign'));
        $delay = max(1, (int) $this->option('delay'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));

        $campaign = Campaign::where('code', $code)->first();
        if (! $campaign) {
            $this->error("Campaign not found: {$code}");

            return self::FAILURE;
        }

        $campaign->update([
            'predictive_enabled' => true,
            'predictive_delay_seconds' => $delay,
            'predictive_max_attempts' => $maxAttempts,
        ]);

        $this->info("Predictive dialing enabled for {$code} (delay={$delay}s, max_attempts={$maxAttempts}).");

        return self::SUCCESS;
    }
}
