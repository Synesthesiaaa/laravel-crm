<?php

namespace App\Jobs;

use App\Events\LeadImported;
use App\Imports\LeadsImport;
use App\Models\LeadList;
use App\Services\Leads\LeadImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportLeadsFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 1800;

    /**
     * @param  array<string, string>  $mapping
     * @param  array{dedupe: ?string, update_existing: bool}  $options
     */
    public function __construct(
        public int $listId,
        public string $token,
        public array $mapping,
        public array $options,
        public int $uploadedByUserId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(LeadImportService $service): void
    {
        $list = LeadList::find($this->listId);
        if (! $list) {
            Log::warning('ImportLeadsFileJob: list not found', ['list_id' => $this->listId]);

            return;
        }

        $path = $service->resolveStashPath($this->token);
        if (! $path) {
            Log::warning('ImportLeadsFileJob: stash file not found', ['token' => $this->token]);

            return;
        }

        $import = new LeadsImport($list, $this->mapping, $this->options, $service);

        try {
            Excel::import($import, $path);
        } catch (\Throwable $e) {
            Log::error('ImportLeadsFileJob: import failed', [
                'list_id' => $this->listId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        LeadImported::dispatch($list->campaign_code, $import->inserted + $import->updated, $this->uploadedByUserId);

        Log::info('ImportLeadsFileJob: finished', [
            'list_id' => $this->listId,
            'inserted' => $import->inserted,
            'updated' => $import->updated,
            'skipped' => $import->skipped,
        ]);

        $service->deleteStash($this->token);
    }
}
