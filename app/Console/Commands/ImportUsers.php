<?php

namespace App\Console\Commands;

use App\Services\Imports\CsvUserImporter;
use App\Services\Imports\InvalidUserImportInput;
use Illuminate\Console\Command;
use RuntimeException;

class ImportUsers extends Command
{
    protected $signature = 'users:import
        {file : Path to a local UTF-8 CSV file}
        {--dry-run : Validate and count without creating CRM accounts}
        {--chunk=500 : Number of valid rows per batch (1-1000)}
        {--errors= : Create a new CSV containing record numbers and safe errors}
        {--allow-privileged-roles : Allow Team Leader, Admin, and Super Admin rows}';

    protected $description = 'Stream a large CSV into CRM users without updating accounts or provisioning VICIdial';

    public function handle(CsvUserImporter $importer): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($chunk === false) {
            $this->error('--chunk must be an integer between 1 and 1000.');

            return self::INVALID;
        }
        $report = null;
        $shown = 0;
        $dryRun = (bool) $this->option('dry-run');

        try {
            if ($this->option('errors') !== null) {
                $reportPath = (string) $this->option('errors');
                if ($reportPath === '' || str_contains($reportPath, '://')) {
                    throw new InvalidUserImportInput('--errors must name a new local CSV file.');
                }
                // Exclusive creation prevents overwriting the source, another report, or a symlink.
                $report = @fopen($reportPath, 'xb');
                if ($report === false) {
                    throw new InvalidUserImportInput('Cannot create the error report. Use a new path in a writable directory.');
                }
                if (fputcsv($report, ['record', 'errors'], ',', '"', '') === false) {
                    throw new RuntimeException('Cannot write the error report.');
                }
            }

            $counts = $importer->import(
                (string) $this->argument('file'), $chunk, $dryRun, (bool) $this->option('allow-privileged-roles'),
                function (int $record, string $errors) use ($report, &$shown): void {
                    if (is_resource($report) && fputcsv($report, [$record, $errors], ',', '"', '') === false) {
                        throw new RuntimeException('Cannot write the error report.');
                    }
                    if ($shown++ < 10) {
                        $this->warn('Record '.$record.': '.$errors);
                    }
                },
                function (array $counts): void {
                    $this->output->writeln('Processed '.$counts['processed'].' records...', \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE);
                },
            );
            $this->summary($counts, $dryRun);
            if ($shown > 10) {
                $this->line('Only the first 10 row errors were shown. Use --errors for a complete report.');
            }

            return $counts['rejected'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (InvalidUserImportInput $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (\Throwable) {
            // SQL exceptions may contain plaintext CSV credentials in their bindings.
            $this->error('Import stopped. Check database access, pdo_sqlite, APP_KEY, and temporary/report disk space. Completed accounts are retained; rerun to skip them.');
            $this->summary($importer->counts(), $dryRun);

            return self::FAILURE;
        } finally {
            if (is_resource($report)) {
                fclose($report);
            }
        }
    }

    private function summary(array $counts, bool $dryRun): void
    {
        $this->line(sprintf('Processed: %d | %s: %d | Skipped: %d | Rejected: %d',
            $counts['processed'], $dryRun ? 'Eligible' : 'Created', $counts['created'], $counts['skipped'], $counts['rejected']));
    }
}
