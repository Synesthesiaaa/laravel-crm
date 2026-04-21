<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadList;
use App\Support\OperationResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LeadImportService
{
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

        $token = (string) Str::uuid();
        $path = 'lead-imports/'.$token.'.'.$ext;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $absolute = Storage::disk('local')->path($path);
        $sheets = Excel::toArray(null, $absolute);
        $rows = $sheets[0] ?? [];
        $headers = array_map(
            fn ($h) => is_string($h) ? trim($h) : (string) $h,
            array_values($rows[0] ?? [])
        );
        $preview = array_slice($rows, 1, 5);

        return [
            'token' => $token,
            'path' => $path,
            'headers' => $headers,
            'preview' => $preview,
            'rows' => max(0, count($rows) - 1),
            'campaign_code' => $campaignCode,
        ];
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
            foreach ($rows as $row) {
                $phone = trim((string) ($row['phone_number'] ?? ''));
                if ($phone === '') {
                    $skipped++;

                    continue;
                }

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
