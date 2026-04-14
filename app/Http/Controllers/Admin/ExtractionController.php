<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtractionRequest;
use App\Services\CampaignService;
use App\Services\ExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtractionController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected ExtractionService $extractionService
    ) {}

    public function index(Request $request): Response
    {
        $campaign       = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];

        return $this->inertiaAdmin('admin.inline-extraction', [
            'campaign'     => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms'        => $campaignConfig['forms'] ?? [],
        ], 'Data Extraction');
    }

    public function export(ExtractionRequest $request): StreamedResponse|RedirectResponse
    {
        $campaign       = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $dataType       = $request->input('data_type', 'all');
        $tables         = $this->extractionService->resolveTables($campaignConfig, $dataType);

        if (empty($tables)) {
            return redirect()->route('admin.extraction.index')->with('error', 'No tables to export.');
        }

        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $filename  = 'export_' . $campaign . '_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($tables, $startDate, $endDate) {
            $out = fopen('php://output', 'w');
            $this->extractionService->streamCsv($out, $tables, $startDate, $endDate);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
