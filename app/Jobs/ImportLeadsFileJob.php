<?php

namespace App\Jobs;

use App\Events\LeadImported;
use App\Imports\LeadsImport;
use App\Models\LeadList;
use App\Services\Leads\HopperLoaderService;
use App\Services\Leads\LeadImportProgressTracker;
use App\Services\Leads\LeadImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
        public string $runId,
        public int $estimatedRows = 0,
    ) {
        $this->onQueue('imports');
    }

    public function handle(LeadImportService $service, HopperLoaderService $hopperLoader): void
    {
        $tracker = app(LeadImportProgressTracker::class);

        $list = LeadList::find($this->listId);
        if (! $list) {
            Log::warning('ImportLeadsFileJob: list not found', ['list_id' => $this->listId]);
            $tracker->fail($this->runId, 'Lead list was deleted before the import could run.');
            throw new \RuntimeException('Lead list not found for import job.');
        }

        $path = $service->resolveStashPath($this->token);
        if (! $path) {
            Log::warning('ImportLeadsFileJob: stash file not found', ['token' => $this->token]);
            $tracker->fail($this->runId, 'Uploaded file expired or was removed before processing started. Ensure the web app and Horizon workers share the same storage disk (e.g. NFS or `local` on one server).');
            throw new \RuntimeException('Lead import stash file not found — check APP_ENV storage path and queue worker filesystem.');
        }

        $tracker->markProcessing($this->runId);

        $uploader = \App\Models\User::find($this->uploadedByUserId);
        $this->options['allow_status_override'] = $uploader?->isSuperAdmin() ?? false;

        $import = new LeadsImport(
            $list,
            $this->mapping,
            $this->options,
            $service,
            $this->runId,
            $tracker,
        );

        try {
            Excel::import($import, $path);
        } catch (\Throwable $e) {
            Log::error('ImportLeadsFileJob: import failed', [
                'list_id' => $this->listId,
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);
            $tracker->fail($this->runId, $e->getMessage());
            throw $e;
        }

        $tracker->complete(
            $this->runId,
            $import->inserted,
            $import->updated,
            $import->skipped,
            $import->failedChunks,
        );

        LeadImported::dispatch($list->campaign_code, $import->inserted + $import->updated, $this->uploadedByUserId);

        Log::info('ImportLeadsFileJob: finished', [
            'list_id' => $this->listId,
            'run_id' => $this->runId,
            'inserted' => $import->inserted,
            'updated' => $import->updated,
            'skipped' => $import->skipped,
        ]);

        // Import now suppresses per-row hopper dispatches; do one efficient top-up
        // after commit so NEW/CALLBK leads are queued in batch.
        if (($import->inserted + $import->updated) > 0) {
            $hopperLoader->loadList($list, 500);
        }

        $service->deleteStash($this->token);
    }

    public function failed(?\Throwable $e = null): void
    {
        if ($e === null) {
            return;
        }

        $tracker = app(LeadImportProgressTracker::class);
        $existing = $tracker->get($this->runId);
        if ($existing !== null && ($existing['status'] ?? '') === 'completed') {
            return;
        }

        $tracker->fail($this->runId, $e->getMessage());
    }
}
