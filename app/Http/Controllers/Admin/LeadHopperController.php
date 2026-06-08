<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportLeadsCsvJob;
use App\Models\LeadHopper;
use App\Models\LeadImport;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LeadHopperController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];

        $statusCounts = [];
        $recentLeads = collect();
        $imports = collect();
        $schemaReady = Schema::hasTable('lead_hopper') && Schema::hasTable('lead_imports');

        if ($schemaReady) {
            $statusCounts = LeadHopper::query()
                ->where('campaign_code', $campaign)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('total', 'status')
                ->map(fn ($value) => (int) $value)
                ->all();

            $recentLeads = LeadHopper::query()
                ->where('campaign_code', $campaign)
                ->latest()
                ->limit(25)
                ->get();

            $imports = LeadImport::query()
                ->where('campaign_code', $campaign)
                ->with('uploadedBy')
                ->latest()
                ->limit(15)
                ->get();
        }

        return view('admin.lead_hopper', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms' => $forms,
            'statusCounts' => $statusCounts,
            'recentLeads' => $recentLeads,
            'imports' => $imports,
            'schemaReady' => $schemaReady,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];

        if (! Schema::hasTable('lead_hopper') || ! Schema::hasTable('lead_imports')) {
            return redirect()->route('admin.lead-hopper.index')->with('error', 'Lead import schema is not ready. Run migrations first.');
        }

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'max:50'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $formType = (string) $validated['form_type'];
        if (! isset($forms[$formType])) {
            return redirect()->route('admin.lead-hopper.index')->with('error', 'Invalid form type for this campaign.');
        }

        $file = $request->file('csv_file');
        $storedPath = $file->storeAs(
            'lead-imports',
            Str::uuid()->toString().'.csv',
            'local',
        );

        $leadImport = LeadImport::create([
            'uploaded_by_user_id' => $request->user()?->id,
            'campaign_code' => $campaign,
            'form_type' => $formType,
            'status' => LeadImport::STATUS_QUEUED,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
        ]);

        ImportLeadsCsvJob::dispatch(
            Storage::disk('local')->path($storedPath),
            $campaign,
            $formType,
            $request->user()?->username ?? 'system',
            $leadImport->id,
        );

        return redirect()->route('admin.lead-hopper.index')->with('success', 'Lead import queued.');
    }
}
