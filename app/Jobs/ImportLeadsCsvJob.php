<?php

namespace App\Jobs;

use App\Models\LeadImport;
use App\Models\LeadImportRow;
use App\Services\CampaignService;
use App\Services\FormSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use League\Csv\Reader;

class ImportLeadsCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

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
        if (! $leadImport) {
            Log::warning('ImportLeadsCsvJob: Import tracking record is missing.', [
                'lead_import_id' => $this->leadImportId,
            ]);

            return;
        }

        if (! Schema::hasTable('lead_import_rows')) {
            $this->markFailed('Import row tracking is unavailable.');

            return;
        }

        if ($leadImport->campaign_code !== $this->campaignCode || $leadImport->form_type !== $this->formType) {
            $this->markFailed('Import metadata did not match the queued job.');

            return;
        }

        $leadImport->update([
            'status' => LeadImport::STATUS_PROCESSING,
            'started_at' => $leadImport->started_at ?? now(),
            'finished_at' => null,
            'error_summary' => null,
        ]);

        if (! $campaignService->getFormConfig($this->campaignCode, $this->formType)) {
            Log::warning('ImportLeadsCsvJob: Invalid campaign/form.', [
                'lead_import_id' => $leadImport->id,
                'campaign' => $this->campaignCode,
                'form' => $this->formType,
            ]);
            $this->markFailed('Invalid campaign or form.');

            return;
        }

        if (! file_exists($this->filePath) || ! is_readable($this->filePath)) {
            Log::warning('ImportLeadsCsvJob: CSV file is missing or unreadable.', [
                'lead_import_id' => $leadImport->id,
            ]);
            $this->markFailed('CSV file was not found or is not readable.');

            return;
        }

        try {
            $reader = Reader::createFromPath($this->filePath, 'r');
            $reader->setHeaderOffset(0);
        } catch (\Throwable $exception) {
            Log::warning('ImportLeadsCsvJob: Failed to open CSV.', [
                'lead_import_id' => $leadImport->id,
                'error_class' => $exception::class,
            ]);
            $this->markFailed('CSV file could not be opened.');

            return;
        }

        $rowNumber = 0;
        $defaultDate = $leadImport->created_at?->toDateString() ?? now()->toDateString();
        foreach ($reader->getRecords() as $record) {
            $rowNumber++;
            $requestId = "lead-import-{$leadImport->id}-row-{$rowNumber}";
            if (LeadImportRow::query()
                ->where('lead_import_id', $leadImport->id)
                ->where('row_number', $rowNumber)
                ->exists()) {
                continue;
            }

            $data = is_array($record) ? $record : iterator_to_array($record);
            $data['date'] = $data['date'] ?? $defaultDate;
            unset($data['request_id']);

            try {
                $result = $formSubmissionService->submitImportRow(
                    $this->campaignCode,
                    $this->formType,
                    $data,
                    $this->agent,
                    $leadImport->uploaded_by_user_id,
                    $leadImport->id,
                    $rowNumber,
                    $requestId,
                );

                if (! $result->success) {
                    $this->recordFailedRow($leadImport->id, $rowNumber, $requestId, 'invalid_submission');
                }
            } catch (\InvalidArgumentException) {
                $this->recordFailedRow($leadImport->id, $rowNumber, $requestId, 'invalid_submission');
            }
        }

        $this->finishFromReceipts($leadImport);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('ImportLeadsCsvJob: Queue attempt failed.', [
            'lead_import_id' => $this->leadImportId,
            'error_class' => $exception::class,
        ]);
        $this->markFailed('The import could not be completed due to a processing error.');
    }

    private function leadImport(): ?LeadImport
    {
        return $this->leadImportId ? LeadImport::find($this->leadImportId) : null;
    }

    private function recordFailedRow(int $leadImportId, int $rowNumber, string $requestId, string $failureCode): void
    {
        DB::transaction(function () use ($leadImportId, $rowNumber, $requestId, $failureCode): void {
            LeadImportRow::query()->firstOrCreate(
                ['lead_import_id' => $leadImportId, 'row_number' => $rowNumber],
                [
                    'request_id' => $requestId,
                    'status' => LeadImportRow::STATUS_FAILED,
                    'failure_code' => $failureCode,
                ],
            );
        });
    }

    private function finishFromReceipts(LeadImport $leadImport): void
    {
        $imported = LeadImportRow::query()
            ->where('lead_import_id', $leadImport->id)
            ->where('status', LeadImportRow::STATUS_IMPORTED)
            ->count();
        $failed = LeadImportRow::query()
            ->where('lead_import_id', $leadImport->id)
            ->where('status', LeadImportRow::STATUS_FAILED)
            ->count();

        $leadImport->update([
            'status' => $failed > 0 ? LeadImport::STATUS_FAILED : LeadImport::STATUS_COMPLETED,
            'imported_count' => $imported,
            'failed_count' => $failed,
            'error_summary' => $failed > 0 ? "{$failed} row(s) failed validation." : null,
            'finished_at' => now(),
        ]);

        Log::info('ImportLeadsCsvJob: CSV import processed.', [
            'lead_import_id' => $leadImport->id,
            'imported_count' => $imported,
            'failed_count' => $failed,
        ]);
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
