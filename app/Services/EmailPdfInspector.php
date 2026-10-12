<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class EmailPdfInspector
{
    public function inspect(string $path): void
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) < 8) {
            $this->invalid('A required PDF is empty or unreadable.');
        }
        $handle = fopen($path, 'rb');

        try {
            $signature = fread($handle, 8);
        } finally {
            fclose($handle);
        }
        if (! str_starts_with($signature, '%PDF-') || (new \finfo(FILEINFO_MIME_TYPE))->file($path) !== 'application/pdf') {
            $this->invalid('A document is not a PDF.');
        }

        try {
            $process = new Process([config('email_campaigns.pdfinfo'), $path], null, ['LC_ALL' => 'C']);
            $process->setTimeout(20)->run();
            if (! $process->isSuccessful() || trim($process->getErrorOutput()) !== ''
                || ! preg_match('/^Pages:\s+[1-9][0-9]*$/m', $process->getOutput())
                || ! preg_match('/^Encrypted:\s+no\s*$/m', $process->getOutput())) {
                $this->invalid('A PDF is corrupt, encrypted or cannot be verified.');
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            $this->invalid('PDF verification is unavailable. Configure the approved Poppler pdfinfo executable.');
        }
        $scanner = config('email_campaigns.scanner');
        if (! $scanner && config('email_campaigns.scan_required')) {
            $this->invalid('Required document scanning is unavailable.');
        }
        if ($scanner) {
            try {
                $scan = new Process([$scanner, '--no-summary', $path]);
                $scan->setTimeout(30)->run();
                if (! $scan->isSuccessful()) {
                    $this->invalid('Document security scan did not pass.');
                }
            } catch (Throwable) {
                $this->invalid('Document security scan did not pass.');
            }
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['documents' => $message]);
    }
}
