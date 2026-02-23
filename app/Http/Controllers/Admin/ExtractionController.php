<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtractionController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        return view('admin.extraction', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms' => $forms,
        ]);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $dataType = $request->input('data_type', 'all');
        $allowedTables = $this->campaignService->getAllFormTableNames();
        $tables = [];
        if ($dataType === 'all') {
            foreach ($campaignConfig['forms'] ?? [] as $formCode => $formConfig) {
                $table = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
                if ($table && in_array($table, $allowedTables, true)) {
                    $tables[$table] = $formConfig['name'] ?? $formCode;
                }
            }
        } elseif (isset($campaignConfig['forms'][$dataType])) {
            $table = $campaignConfig['forms'][$dataType]['table_name'] ?? $campaignConfig['forms'][$dataType]['table'] ?? '';
            if ($table && in_array($table, $allowedTables, true)) {
                $tables[$table] = $campaignConfig['forms'][$dataType]['name'] ?? $dataType;
            }
        }
        if (empty($tables)) {
            return redirect()->route('admin.extraction.index')->with('error', 'No tables to export.');
        }
        $filename = 'export_' . $campaign . '_' . date('Y-m-d_His') . '.csv';
        return response()->streamDownload(function () use ($tables, $startDate, $endDate) {
            $out = fopen('php://output', 'w');
            foreach ($tables as $tableName => $friendlyName) {
                $query = DB::table($tableName)->orderBy('id');
                if ($startDate && $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('date', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('date', '<=', $endDate);
                }
                $rows = $query->get();
                if ($rows->isEmpty()) {
                    continue;
                }
                fputcsv($out, array_keys((array) $rows->first()));
                foreach ($rows as $row) {
                    fputcsv($out, (array) $row);
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
