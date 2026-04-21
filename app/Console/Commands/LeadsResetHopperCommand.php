<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Leads\HopperLoaderService;
use Illuminate\Console\Command;

class LeadsResetHopperCommand extends Command
{
    protected $signature = 'leads:reset-hopper {--campaign= : Limit to a single campaign code}
                            {--per-list=500 : Max leads to push per enabled list}';

    protected $description = 'Refill the lead hopper from enabled lists of active campaigns (ViciDial-style).';

    public function handle(HopperLoaderService $loader): int
    {
        $query = Campaign::query()->where('is_active', true);
        if ($code = $this->option('campaign')) {
            $query->where('code', $code);
        }

        $campaigns = $query->get(['id', 'code', 'name']);
        if ($campaigns->isEmpty()) {
            $this->warn('No active campaigns match.');

            return self::SUCCESS;
        }

        $perList = max(1, (int) $this->option('per-list'));
        $total = 0;

        foreach ($campaigns as $campaign) {
            $count = $loader->loadCampaign($campaign->code, $perList);
            $this->info(sprintf('[%s] pushed %d lead(s) to the hopper.', $campaign->code, $count));
            $total += $count;
        }

        $this->info("Done. Total pushed: {$total}.");

        return self::SUCCESS;
    }
}
