<?php

namespace App\Imports;

use App\Models\LeadList;
use App\Services\Leads\LeadImportService;
use Illuminate\Support\Collection;
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

    /**
     * @param  array<string, string>  $mapping  file_header_slug => target_field_key
     * @param  array{dedupe: ?string, update_existing: bool}  $options
     */
    public function __construct(
        protected LeadList $list,
        protected array $mapping,
        protected array $options,
        protected LeadImportService $service,
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $mapped = [];
        foreach ($rows as $row) {
            $source = $row->all();
            $target = [];
            foreach ($this->mapping as $from => $to) {
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
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headingRow(): int
    {
        return 1;
    }
}
