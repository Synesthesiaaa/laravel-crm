<?php

namespace App\Console\Commands;

use App\Jobs\SyncVicidialCallHistoryJob;
use App\Models\Campaign;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncVicidialCallHistory extends Command
{
    protected $signature = 'vicidial:sync-call-history
                            {--server= : Limit dispatch to a VICIdial server ID}
                            {--campaign= : Limit dispatch to a CRM campaign code}
                            {--from= : Backfill start date in Y-m-d format}
                            {--to= : Backfill end date in Y-m-d format}
                            {--recent : Dispatch the configured recent synchronization window}';

    protected $description = 'Dispatch local VICIdial call-history synchronization jobs.';

    public function handle(CrmCampaignVicidialScopeResolver $scopeResolver): int
    {
        $from = $this->dateOption('from');
        $to = $this->dateOption('to');
        if (($this->option('from') !== null && $from === null) || ($this->option('to') !== null && $to === null)) {
            return self::FAILURE;
        }
        if (($from === null) !== ($to === null)) {
            $this->error('Backfill requires both --from and --to.');

            return self::FAILURE;
        }
        if ($from !== null && $from->greaterThan($to)) {
            $this->error('The --from date must be before or equal to --to.');

            return self::FAILURE;
        }

        $campaigns = Campaign::query()->active()->ordered();
        if (($campaign = trim((string) $this->option('campaign'))) !== '') {
            $campaigns->whereRaw('LOWER(code) = ?', [strtolower($campaign)]);
        }
        $serverId = $this->option('server') !== null ? (int) $this->option('server') : null;
        $dispatched = 0;

        foreach ($campaigns->get() as $campaignModel) {
            $scope = $scopeResolver->resolve($campaignModel);
            if ($scope->server === null || $scope->historicalCampaignCodes() === []) {
                continue;
            }
            if ($serverId !== null && (int) $scope->server->getKey() !== $serverId) {
                continue;
            }

            if ($from === null) {
                SyncVicidialCallHistoryJob::dispatch(
                    (int) $campaignModel->getKey(),
                    serverId: (int) $scope->server->getKey(),
                );
                $dispatched++;

                continue;
            }

            $backfillJobs = [];
            for ($windowStart = $from->copy(); $windowStart->lessThanOrEqualTo($to); $windowStart->addDay()) {
                $windowEnd = $windowStart->copy()->endOfDay();
                if ($windowEnd->greaterThan($to)) {
                    $windowEnd = $to->copy();
                }
                $backfillJobs[] = new SyncVicidialCallHistoryJob(
                    (int) $campaignModel->getKey(),
                    $windowStart->toIso8601String(),
                    $windowEnd->toIso8601String(),
                    (int) $scope->server->getKey(),
                );
                $dispatched++;
            }
            if ($backfillJobs !== []) {
                Bus::chain($backfillJobs)->onQueue('telephony')->dispatch();
            }
        }

        $this->info("Dispatched {$dispatched} call-history synchronization job(s).");

        return self::SUCCESS;
    }

    protected function dateOption(string $name): ?Carbon
    {
        $value = trim((string) $this->option($name));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value, (string) config('vicidial.report_timezone', config('app.timezone', 'UTC')));
        } catch (\Throwable) {
            $this->error("The --{$name} date must use Y-m-d format.");

            return null;
        }
    }
}
