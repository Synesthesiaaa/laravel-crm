<?php

namespace App\Exports;

use App\Models\Lead;
use App\Services\Leads\LeadExportService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeadsExport implements FromQuery, WithHeadings, WithMapping
{
    protected array $columns = [];

    protected array $headers = [];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected string $campaignCode,
        protected array $filters,
        protected LeadExportService $service,
    ) {
        $layout = $this->service->buildColumns($campaignCode);
        $this->columns = $layout['columns'];
        $this->headers = $layout['headers'];
    }

    public function query()
    {
        return $this->service->query($this->campaignCode, $this->filters);
    }

    public function headings(): array
    {
        return $this->headers;
    }

    /**
     * @param  Lead  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $out = [];
        foreach ($this->columns as $col) {
            $value = $this->service->valueFor($row, $col);
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $out[] = $value;
        }

        return $out;
    }
}
