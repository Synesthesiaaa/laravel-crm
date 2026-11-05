<?php

namespace App\Console\Commands;

use App\Services\Telephony\CallMetricEvidenceService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportCallMetricEvidence extends Command
{
    protected $signature = 'telephony:import-metric-evidence {file : Path to an attested normalized JSON batch}';

    protected $description = 'Import verified call evidence and optional completeness coverage without disposition inference';

    public function handle(CallMetricEvidenceService $service): int
    {
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 20 * 1024 * 1024) {
            $this->error('Provide a readable JSON file smaller than 20 MB.');

            return self::FAILURE;
        }

        try {
            $batch = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($batch)) {
                $this->error('The JSON batch must be an object.');

                return self::FAILURE;
            }
            $result = $service->import($batch);
        } catch (JsonException|ValidationException $exception) {
            $this->error($exception instanceof ValidationException ? json_encode($exception->errors()) : 'Invalid JSON batch.');

            return self::FAILURE;
        }
        $this->info('Imported '.$result['attempts_written'].' attempt projections; source watermark '.$result['observed_at'].'.');

        return self::SUCCESS;
    }
}
