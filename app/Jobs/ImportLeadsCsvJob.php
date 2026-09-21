<?php

namespace App\Jobs;

use App\Models\LeadImport;
use App\Services\CampaignService;
use App\Services\FormSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class ImportLeadsCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of attempts. */
    public int $tries = 2;

    /** Seconds to wait before retry. */
    public int $backoff = 60;

    /** Job timeout in seconds (large CSV imports). */
    public int $timeout = 600;

    public function __construct(
        public string $filePath,
        public string $campaignCode,
        public string $formType,
        public string $agent,
        public ?int $leadImportId = null,
    ) {
        $this->onQueue('imports');
    }

    public function handle(CampaignService $campaignService, FormSubmissionService $formSubmissionService): void
    {
        $leadImport = $this->leadImport();
        $leadImport?->update([
            'status' => LeadImport::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $formConfig = $campaignService->getFormConfig($this->campaignCode, $this->formType);
        if (! $formConfig) {
            Log::warning('ImportLeadsCsvJob: Invalid campaign/form', ['campaign' => $this->campaignCode, 'form' => $this->formType]);
            $this->markFailed('Invalid campaign or form.');

            return;
        }
        if (! file_exists($this->filePath) || ! is_readable($this->filePath)) {
            Log::warning('ImportLeadsCsvJob: File not found or not readable', ['path' => $this->filePath]);
            $this->markFailed('CSV file was not found or is not readable.');

            return;
        }

        try {
            $reader = Reader::createFromPath($this->filePath, 'r');
            $reader->setHeaderOffset(0);
        } catch (\Throwable $e) {
            Log::warning('ImportLeadsCsvJob: Failed to open CSV', ['path' => $this->filePath, 'error' => $e->getMessage()]);
            $this->markFailed('Failed to open CSV: '.$e->getMessage());

            return;
        }

        $count = 0;
        $failed = 0;
        foreach ($reader->getRecords() as $record) {
            $data = is_array($record) ? $record : iterator_to_array($record);
            $data['date'] = $data['date'] ?? now()->format('Y-m-d');
            unset($data['request_id']);
            $result = $formSubmissionService->submit($this->campaignCode, $this->formType, $data, $this->agent);
            if ($result->success) {
                $count++;
            } else {
                $failed++;
            }
        }

        $leadImport?->update([
            'status' => $failed > 0 ? LeadImport::STATUS_FAILED : LeadImport::STATUS_COMPLETED,
            'imported_count' => $count,
            'failed_count' => $failed,
            'error_summary' => $failed > 0 ? "{$failed} row(s) failed validation." : null,
            'finished_at' => now(),
        ]);

        Log::info('ImportLeadsCsvJob: Imported '.$count.' rows', ['campaign' => $this->campaignCode, 'form' => $this->formType]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    private function leadImport(): ?LeadImport
    {
        return $this->leadImportId ? LeadImport::find($this->leadImportId) : null;
    }

    private function markFailed(string $message): void
    {
        $this->leadImport()?->update([
            'status' => LeadImport::STATUS_FAILED,
            'error_summary' => $message,
            'finished_at' => now(),
        ]);
    }
}
