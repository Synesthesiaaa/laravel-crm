<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EmailRecipientCsvImporter
{
    public function read(string $path, bool $personal, bool $payroll): array
    {
        $file = fopen($path, 'rb');
        if (! $file) {
            $this->invalid('CSV could not be read.');
        }

        try {
            $header = array_map(fn ($v) => strtolower(trim((string) $v, "\xEF\xBB\xBF \t\r\n")), fgetcsv($file, 0, ',', '"', '') ?: []);
            $required = $personal ? ['email', 'pdf_filename'] : ['email'];
            if ($payroll) {
                $required = ['email', 'pdf_filename', 'employee_id', 'period'];
            }
            if (count($header) !== count(array_unique($header)) || array_diff($required, $header)
                || array_diff($header, ['email', 'name', 'employee_id', 'period', 'pdf_filename'])) {
                $this->invalid('CSV requires unique supported headers: '.implode(', ', $required).'.');
            }
            $rows = $seen = $files = [];
            $line = 1;
            while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    $this->invalid("Malformed CSV row {$line}.");
                }
                $row = array_map(fn ($v) => trim((string) $v), array_combine($header, $values));
                foreach ($row as $value) {
                    if (! mb_check_encoding($value, 'UTF-8') || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) || preg_match('/^[=+@-]/u', $value)) {
                        $this->invalid("Unsafe or oversized value at CSV row {$line}.");
                    }
                }
                if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->invalid("Invalid email at CSV row {$line}.");
                }
                [$local, $domain] = explode('@', $row['email'], 2);
                $row['email'] = $local.'@'.strtolower($domain);
                $key = strtolower($row['email']);
                if (isset($seen[$key])) {
                    $this->invalid("Duplicate recipient at CSV row {$line}.");
                }
                foreach ($required as $field) {
                    if (($row[$field] ?? '') === '') {
                        $this->invalid("Missing {$field} at CSV row {$line}.");
                    }
                }
                if ($personal) {
                    EmailCampaignAttachmentService::validateFilename($row['pdf_filename']);
                    $fileKey = mb_strtolower($row['pdf_filename']);
                    if (isset($files[$fileKey])) {
                        $this->invalid("Duplicate PDF mapping at CSV row {$line}.");
                    }
                    $files[$fileKey] = true;
                } elseif (! empty($row['pdf_filename'])) {
                    $this->invalid("Personal PDF requires recipient-specific attachment mode at CSV row {$line}.");
                }
                $seen[$key] = true;
                $rows[] = array_intersect_key($row, array_flip(['email', 'name', 'employee_id', 'period', 'pdf_filename']));
                if (count($rows) > config('email_campaigns.max_recipients') || $line > 50000) {
                    $this->invalid('Recipient limit exceeded.');
                }
            }
            if (! $rows) {
                $this->invalid('CSV has no recipients.');
            }

            return $rows;
        } finally {
            fclose($file);
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['recipient_csv' => $message]);
    }
}
