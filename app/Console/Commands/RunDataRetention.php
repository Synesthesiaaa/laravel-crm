<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class RunDataRetention extends Command
{
    protected $signature = 'data-retention:run';

    protected $description = 'Permanently delete expired records from configured forms.';

    public function handle(DataRetentionService $retentionService): int
    {
        $summary = $retentionService->runDue();

        $this->line('Processed: '.$summary['processed']);
        $this->line('Skipped: '.$summary['skipped']);
        $this->line('Deleted: '.$summary['deleted']);

        return self::SUCCESS;
    }
}
