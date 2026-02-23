<?php

namespace App\Jobs;

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
        public string $agent
    ) {}

    public function handle(CampaignService $campaignService, FormSubmissionService $formSubmissionService): void
    {
        $formConfig = $campaignService->getFormConfig($this->campaignCode, $this->formType);
        if (!$formConfig) {
            Log::warning('ImportLeadsCsvJob: Invalid campaign/form', ['campaign' => $this->campaignCode, 'form' => $this->formType]);
            return;
        }
        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            Log::warning('ImportLeadsCsvJob: File not found or not readable', ['path' => $this->filePath]);
            return;
        }

        try {
            $reader = Reader::createFromPath($this->filePath, 'r');
            $reader->setHeaderOffset(0);
        } catch (\Throwable $e) {
            Log::warning('ImportLeadsCsvJob: Failed to open CSV', ['path' => $this->filePath, 'error' => $e->getMessage()]);
            return;
        }

        $count = 0;
        foreach ($reader->getRecords() as $record) {
            $data = is_array($record) ? $record : iterator_to_array($record);
            $data['date'] = $data['date'] ?? now()->format('Y-m-d');
            $data['request_id'] = $data['request_id'] ?? $formSubmissionService->generateRequestId($formConfig['table_name']);
            $result = $formSubmissionService->submit($this->campaignCode, $this->formType, $data, $this->agent);
            if ($result->success) {
                $count++;
            }
        }

        Log::info('ImportLeadsCsvJob: Imported ' . $count . ' rows', ['campaign' => $this->campaignCode, 'form' => $this->formType]);
    }
}
