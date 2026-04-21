<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Exports\LeadsExport;
use App\Http\Controllers\Controller;
use App\Models\LeadList;
use App\Services\Leads\LeadExportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadExportController extends Controller
{
    public function __construct(
        protected LeadExportService $exportService,
    ) {}

    public function download(Request $request, LeadList $list): BinaryFileResponse
    {
        $this->authorize('export', LeadList::class);

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $writer = match ($format) {
            'csv' => ExcelType::CSV,
            default => ExcelType::XLSX,
        };
        $ext = $writer === ExcelType::CSV ? 'csv' : 'xlsx';

        $filters = [
            'list_id' => $list->id,
            'status' => $request->query('status'),
            'enabled' => $request->query('enabled'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'min_called_count' => $request->query('min_called_count'),
        ];

        $filename = sprintf(
            'leads_%s_list%d_%s.%s',
            $list->campaign_code,
            $list->id,
            now()->format('Ymd_His'),
            $ext,
        );

        return Excel::download(
            new LeadsExport($list->campaign_code, $filters, $this->exportService),
            $filename,
            $writer,
        );
    }

    public function downloadAll(Request $request): BinaryFileResponse
    {
        $this->authorize('export', LeadList::class);

        $campaign = (string) ($request->query('campaign') ?: $request->session()->get('campaign', 'mbsales'));

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $writer = match ($format) {
            'csv' => ExcelType::CSV,
            default => ExcelType::XLSX,
        };
        $ext = $writer === ExcelType::CSV ? 'csv' : 'xlsx';

        $filters = [
            'status' => $request->query('status'),
            'enabled' => $request->query('enabled'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'min_called_count' => $request->query('min_called_count'),
        ];

        $filename = sprintf(
            'leads_%s_all_%s.%s',
            $campaign,
            now()->format('Ymd_His'),
            $ext,
        );

        return Excel::download(
            new LeadsExport($campaign, $filters, $this->exportService),
            $filename,
            $writer,
        );
    }

    public function template(Request $request, LeadList $list): BinaryFileResponse
    {
        $this->authorize('export', LeadList::class);

        $layout = $this->exportService->buildColumns($list->campaign_code);
        $filename = 'leads_template_'.$list->campaign_code.'.csv';

        return Excel::download(
            new class($layout['headers']) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
            {
                public function __construct(protected array $headers) {}

                public function array(): array
                {
                    return [];
                }

                public function headings(): array
                {
                    return $this->headers;
                }
            },
            $filename,
            ExcelType::CSV,
        );
    }
}
