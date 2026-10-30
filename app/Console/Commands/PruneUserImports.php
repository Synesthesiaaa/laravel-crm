<?php

namespace App\Console\Commands;

use App\Services\Imports\UserImportService;
use Illuminate\Console\Command;

class PruneUserImports extends Command
{
    protected $signature = 'users:imports-prune';

    protected $description = 'Remove expired private user uploads and old import reports';

    public function handle(UserImportService $imports): int
    {
        $imports->prune();
        $this->info('Expired user import files and reports pruned.');

        return self::SUCCESS;
    }
}
