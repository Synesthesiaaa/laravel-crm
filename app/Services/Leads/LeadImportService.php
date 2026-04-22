<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadList;
use App\Support\OperationResult;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormats;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class LeadImportService
{
    /**
     * Columns on the Lead model that are cast to date / datetime and therefore
     * cannot accept arbitrary string values from a spreadsheet.
     *
     * @var list<string>
     */
    protected const DATE_COLUMNS = [
        'date_of_birth',
        'last_called_at',
        'last_local_call_time',
    ];

    public function __construct(
        protected LeadFieldService $fieldService,
    ) {}

    /**
     * Store the uploaded import file and return a token that the user can
     * use to confirm / map headers in the next wizard step.
     *
     * @return array{token: string, headers: list<string>, preview: array<int, array<int, mixed>>, rows: int}
     */
    public function stash(UploadedFile $file, string $campaignCode): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'csv');
        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException('Unsupported file type. Use CSV or XLSX.');
        }

        // Defensive: large CSV/XLSX header inspection can spike memory; raise
        // ceilings only for this request so the upload step never 500s on
        // bigger lists. PHP-FPM ignores these in safe mode but for typical
        // installs they take effect for the lifetime of the request.
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $token = (string) Str::uuid();
        $path = 'lead-imports/'.$token.'.'.$ext;

        // Stream the upload straight to storage instead of loading the whole
        // file into RAM via file_get_contents(). storeAs() uses a stream copy
        // under the hood so a 50 MB upload no longer needs 50 MB of memory.
        $file->storeAs('lead-imports', $token.'.'.$ext, 'local');

        $absolute = Storage::disk('local')->path($path);

        try {
            [$headers, $preview, $rowCount] = $ext === 'csv'
                ? $this->inspectCsv($absolute)
                : $this->inspectSpreadsheet($absolute, $ext);
        } catch (\Throwable $e) {
            // Inspection failed (corrupt file, weird encoding, etc.). Drop the
            // stash file so we don't leak orphaned uploads, then surface a
            // friendly error to the controller (which renders a flash msg).
            Storage::disk('local')->delete($path);
            throw new \RuntimeException(
                'Could not read the uploaded file. It may be corrupt, empty, or in an unsupported encoding. ('
                .$e->getMessage().')',
                previous: $e,
            );
        }

        if ($headers === []) {
            Storage::disk('local')->delete($path);
            throw new \RuntimeException('The uploaded file appears to be empty or has no header row.');
        }

        return [
            'token' => $token,
            'path' => $path,
            'headers' => $headers,
            'preview' => $preview,
            'rows' => $rowCount,
            'campaign_code' => $campaignCode,
        ];
    }

    /**
     * Inspect a CSV using native fgetcsv — constant memory regardless of file
     * size, handles quoted fields, and tolerates BOM / mixed line endings.
     *
     * @return array{0: list<string>, 1: array<int, array<int, mixed>>, 2: int}
     */
    protected function inspectCsv(string $absolutePath): array
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open uploaded CSV for inspection.');
        }

        try {
            // Strip UTF-8 BOM if present so the first header isn't "\ufeffName".
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headerRow = fgetcsv($handle);
            if ($headerRow === false || $headerRow === null) {
                return [[], [], 0];
            }
            $headers = array_map(
                fn ($h) => is_string($h) ? trim($h) : (string) $h,
                array_values($headerRow)
            );

            $preview = [];
            $rowCount = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $rowCount++;
                if (count($preview) < 5) {
                    $preview[] = array_values($row);
                }
            }

            return [$headers, $preview, $rowCount];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Inspect an XLSX/XLS by pulling only the heading row and a tiny preview
     * slice, never the entire workbook. Falls back to a chunked Excel read
     * for the row count rather than holding every cell in memory.
     *
     * @return array{0: list<string>, 1: array<int, array<int, mixed>>, 2: int}
     */
    protected function inspectSpreadsheet(string $absolutePath, string $ext): array
    {
        $readerType = $ext === 'xls' ? ExcelFormats::XLS : ExcelFormats::XLSX;

        $heading = (new HeadingRowImport)->toArray($absolutePath, null, $readerType);
        $headerRow = $heading[0][0] ?? [];
        $headers = array_map(
            fn ($h) => is_string($h) ? trim($h) : (string) $h,
            array_values($headerRow)
        );

        // Best-effort preview + row count. We deliberately swallow errors here
        // so a busted preview never crashes the upload step.
        $preview = [];
        $rowCount = 0;
        try {
            $sheets = Excel::toArray(null, $absolutePath, $readerType);
            $rows = $sheets[0] ?? [];
            $rowCount = max(0, count($rows) - 1);
            $preview = array_slice($rows, 1, 5);
        } catch (\Throwable) {
            // Headers are enough to proceed with mapping — preview is cosmetic.
        }

        return [$headers, $preview, $rowCount];
    }

    /**
     * Persist a batch of mapped rows into the leads table.
     *
     * @param  array<int, array<string, mixed>>  $rows  Already-mapped rows where keys match db columns or custom keys.
     * @param  array{dedupe: ?string, update_existing: bool}  $options
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function persistRows(LeadList $list, array $rows, array $options = []): array
    {
        $dedupe = $options['dedupe'] ?? null; // 'phone_number' | 'vendor_lead_code' | null
        $updateExisting = (bool) ($options['update_existing'] ?? false);

        $standardColumns = $this->fieldService->standardColumns();
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $list, $dedupe, $updateExisting, $standardColumns, &$inserted, &$updated, &$skipped) {
            foreach ($rows as $rowIndex => $row) {
                try {
                    foreach ($row as $k => $v) {
                        $row[$k] = $this->normalizeImportCellValue($v);
                    }

                    $phone = $this->stringForPhoneImport($row['phone_number'] ?? null);
                    if ($phone === '') {
                        $skipped++;

                        continue;
                    }
                    $phone = mb_substr($phone, 0, 32);

                    $standard = [];
                    $custom = [];
                    foreach ($row as $key => $value) {
                        if ($key === '' || $key === null) {
                            continue;
                        }
                        if (in_array($key, $standardColumns, true) || in_array($key, ['status', 'enabled'], true)) {
                            $standard[$key] = $value;
                        } else {
                            $custom[$key] = $value;
                        }
                    }

                    $standard = $this->sanitiseDateColumns($standard);

                    $payload = array_merge($standard, [
                        'list_id' => $list->id,
                        'campaign_code' => $list->campaign_code,
                        'phone_number' => $phone,
                        'status' => $standard['status'] ?? 'NEW',
                        'enabled' => (bool) ($standard['enabled'] ?? true),
                        'custom_fields' => $custom !== [] ? $custom : null,
                    ]);

                    $existing = null;
                    if ($dedupe === 'phone_number') {
                        $existing = Lead::forList($list->id)->where('phone_number', $phone)->first();
                    } elseif ($dedupe === 'vendor_lead_code' && ! empty($payload['vendor_lead_code'])) {
                        $existing = Lead::forList($list->id)
                            ->where('vendor_lead_code', $payload['vendor_lead_code'])
                            ->first();
                    }

                    if ($existing) {
                        if ($updateExisting) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            $skipped++;
                        }

                        continue;
                    }

                    Lead::create($payload);
                    $inserted++;
                } catch (\Throwable $e) {
                    // One bad cell should never poison a 100k-row import.
                    $skipped++;
                    Log::warning('LeadImportService: skipped bad row', [
                        'list_id' => $list->id,
                        'row_index' => $rowIndex,
                        'phone' => $row['phone_number'] ?? null,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]);
                }
            }
        });

        $list->update(['leads_count' => $list->leads()->count()]);

        Log::info('LeadImportService: persisted rows', [
            'list_id' => $list->id,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return compact('inserted', 'updated', 'skipped');
    }

    /**
     * Coerce PhpSpreadsheet / Maatwebsite cell payloads into plain PHP values
     * so Eloquent never receives RichText objects, etc.
     */
    protected function normalizeImportCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        if (is_object($value) && method_exists($value, 'getPlainText')) {
            return trim((string) $value->getPlainText());
        }
        if ($value instanceof \Stringable) {
            return trim((string) $value->__toString());
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return $value;
    }

    /**
     * Build a single dialable phone string from whatever Excel/CSV put in the cell.
     */
    protected function stringForPhoneImport(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            // Sheet mis-detected a phone column as a date — do not persist garbage.
            return '';
        }
        if (is_object($value) && method_exists($value, 'getPlainText')) {
            $value = $value->getPlainText();
        } elseif ($value instanceof \Stringable) {
            $value = $value->__toString();
        }
        if (is_float($value) || is_int($value)) {
            if (is_float($value) && is_finite($value) && abs($value - round($value)) < 1e-9) {
                return (string) (int) round($value);
            }
            if (is_float($value) && is_finite($value)) {
                $s = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

                return trim($s);
            }

            return trim((string) $value);
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    /**
     * Apply a column mapping to convert raw sheet rows into db-ready rows.
     *
     * @param  array<int, array<int, mixed>>  $rawRows  Raw sheet rows (header row excluded).
     * @param  list<string>  $headers  Ordered headers from the file.
     * @param  array<string, string>  $mapping  source_header => target_field (db column or custom_key).
     * @return array<int, array<string, mixed>>
     */
    public function applyMapping(array $rawRows, array $headers, array $mapping): array
    {
        $out = [];
        foreach ($rawRows as $row) {
            $mapped = [];
            foreach ($headers as $idx => $header) {
                $target = $mapping[$header] ?? null;
                if (! $target || $target === '__skip__') {
                    continue;
                }
                $mapped[$target] = $row[$idx] ?? null;
            }
            $out[] = $mapped;
        }

        return $out;
    }

    public function resolveStashPath(string $token): ?string
    {
        foreach (['csv', 'xlsx', 'xls'] as $ext) {
            $path = 'lead-imports/'.$token.'.'.$ext;
            if (Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->path($path);
            }
        }

        return null;
    }

    /**
     * Sanest range MySQL will accept in a DATE / DATETIME column without
     * throwing SQLSTATE[22007]. We tighten it further to 1900..2100 because no
     * realistic DOB / call-time value should fall outside that, and this rules
     * out PhpSpreadsheet's "empty date cell" artefact of year -0001 as well as
     * Carbon's tolerant parse of '0000-00-00'.
     */
    private const DATE_MIN_YEAR = 1900;

    private const DATE_MAX_YEAR = 2100;

    /**
     * Replace any value in a date-cast column that isn't a parseable date with
     * null so Eloquent's `asDateTime()` doesn't throw a DateMalformedStringException
     * (e.g. when an email was accidentally mapped to `date_of_birth`) and so
     * MySQL never receives an out-of-range placeholder like '-0001-11-30'.
     *
     * @param  array<string, mixed>  $standard
     * @return array<string, mixed>
     */
    protected function sanitiseDateColumns(array $standard): array
    {
        foreach (self::DATE_COLUMNS as $col) {
            if (! array_key_exists($col, $standard)) {
                continue;
            }
            $value = $standard[$col];
            if ($value === null || $value === '') {
                $standard[$col] = null;

                continue;
            }
            if ($value instanceof \DateTimeInterface) {
                $standard[$col] = $this->isInSaneDateRange($value) ? $value : null;

                continue;
            }
            try {
                $parsed = Carbon::parse((string) $value);
            } catch (\Throwable) {
                $standard[$col] = null;

                continue;
            }
            $standard[$col] = $this->isInSaneDateRange($parsed) ? $parsed : null;
        }

        return $standard;
    }

    /**
     * True when the given date falls inside the range MySQL will accept and
     * that makes sense for CRM data (DOBs, call timestamps, etc.).
     */
    private function isInSaneDateRange(\DateTimeInterface $dt): bool
    {
        $year = (int) $dt->format('Y');

        return $year >= self::DATE_MIN_YEAR && $year <= self::DATE_MAX_YEAR;
    }

    public function deleteStash(string $token): void
    {
        foreach (['csv', 'xlsx', 'xls'] as $ext) {
            $path = 'lead-imports/'.$token.'.'.$ext;
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return OperationResult with `data => [file: string]`
     */
    public function success(int $inserted, int $updated, int $skipped): OperationResult
    {
        return OperationResult::success(
            ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped],
            sprintf('Imported %d, updated %d, skipped %d.', $inserted, $updated, $skipped),
        );
    }
}
