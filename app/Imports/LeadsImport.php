<?php

namespace App\Imports;

use App\Models\LeadList;
use App\Services\Leads\LeadImportProgressTracker;
use App\Services\Leads\LeadImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LeadsImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    use Importable;

    public int $inserted = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failedChunks = 0;

    /**
     * Mapping with keys normalised to match WithHeadingRow's slug formatter.
     *
     * @var array<string, string>
     */
    protected array $normalisedMapping;

    /**
     * @param  array<string, string>  $mapping  source_header (any case) => target_field_key | "__skip__"
     * @param  array{dedupe: ?string, update_existing: bool}  $options
     */
    public function __construct(
        protected LeadList $list,
        protected array $mapping,
        protected array $options,
        protected LeadImportService $service,
        protected ?string $runId = null,
        protected ?LeadImportProgressTracker $progress = null,
    ) {
        $this->normalisedMapping = $this->normaliseMapping($mapping);
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        try {
            $mapped = [];
            foreach ($rows as $row) {
                $source = $row->all();
                $target = [];
                foreach ($this->normalisedMapping as $from => $to) {
                    if ($to === '' || $to === null || $to === '__skip__') {
                        continue;
                    }
                    if (! array_key_exists($from, $source)) {
                        continue;
                    }
                    $target[$to] = $source[$from];
                }
                if ($target !== []) {
                    $mapped[] = $target;
                }
            }

            if ($mapped === []) {
                return;
            }

            $result = $this->service->persistRows($this->list, $mapped, $this->options);
            $this->inserted += $result['inserted'];
            $this->updated += $result['updated'];
            $this->skipped += $result['skipped'];

            $this->pushProgress(count($mapped), $mapped);
        } catch (\Throwable $e) {
            // Don't let one chunk poison the entire import. Log + skip the chunk.
            $this->failedChunks++;
            $this->skipped += $rows->count();
            Log::error('LeadsImport: chunk failed, skipped', [
                'list_id' => $this->list->id,
                'chunk_size' => $rows->count(),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $this->pushProgress($rows->count(), []);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     */
    protected function pushProgress(int $chunkRowCount, array $mapped): void
    {
        if ($this->runId === null || $this->runId === '' || $this->progress === null) {
            return;
        }

        $recent = [];
        foreach (array_slice($mapped, -8) as $r) {
            $fn = isset($r['first_name']) ? trim((string) $r['first_name']) : '';
            $ln = isset($r['last_name']) ? trim((string) $r['last_name']) : '';
            $name = trim($fn.' '.$ln);
            if ($name === '') {
                $name = null;
            }
            $recent[] = [
                'phone' => trim((string) ($r['phone_number'] ?? '')),
                'name' => $name,
            ];
        }

        $this->progress->afterChunk(
            $this->runId,
            $this->list->id,
            $chunkRowCount,
            $recent,
            $this->inserted,
            $this->updated,
            $this->skipped,
            $this->failedChunks,
        );
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * Normalise mapping keys with the same slug rules Maatwebsite's
     * default heading_row formatter applies, so a wizard key like
     * "Phone_Home_1" matches the runtime row key "phone_home_1".
     *
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    protected function normaliseMapping(array $mapping): array
    {
        $out = [];
        foreach ($mapping as $from => $to) {
            $key = Str::slug((string) $from, '_');
            if ($key === '') {
                continue;
            }
            $out[$key] = $to;
        }

        return $out;
    }
}
