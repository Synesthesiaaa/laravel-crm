<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataMasterController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $type = $request->query('type', array_key_first($forms) ?: '');
        if ($type !== '' && !isset($forms[$type])) {
            $type = array_key_first($forms) ?: '';
        }
        $tableName = $forms[$type]['table_name'] ?? $forms[$type]['table'] ?? '';
        $allowedTables = $this->getAllowedTablesForCampaign($campaignConfig);
        $records = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        $columns = [];
        if ($tableName !== '' && in_array($tableName, $allowedTables, true)) {
            try {
                $records = DB::table($tableName)->orderByDesc('id')->paginate(20);
                $first = $records->first();
                if ($first) {
                    $columns = array_keys((array) $first);
                }
            } catch (\Throwable $e) {
                $tableName = '';
            }
        }
        return view('admin.data_master', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'forms' => $forms,
            'type' => $type,
            'tableName' => $tableName,
            'records' => $records,
            'columns' => $columns,
        ]);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $type = $request->query('type', array_key_first($forms) ?: '');
        $tableName = $forms[$type]['table_name'] ?? $forms[$type]['table'] ?? '';
        $allowedTables = $this->getAllowedTablesForCampaign($campaignConfig);
        if ($tableName === '' || !in_array($tableName, $allowedTables, true)) {
            return redirect()->route('admin.data-master.index')->with('error', 'Invalid table.');
        }
        $record = DB::table($tableName)->where('id', $id)->first();
        if (!$record) {
            return redirect()->route('admin.data-master.index', ['type' => $type])->with('error', 'Record not found.');
        }
        $columns = array_keys((array) $record);
        return view('admin.data_master_edit', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'type' => $type,
            'tableName' => $tableName,
            'record' => $record,
            'columns' => $columns,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $allowedTables = $this->getAllowedTablesForCampaign($campaignConfig);
        $tableName = $request->input('_table');
        $id = (int) $request->input('_id');
        if (!$tableName || !in_array($tableName, $allowedTables, true)) {
            return back()->with('error', 'Invalid table.');
        }
        $record = DB::table($tableName)->where('id', $id)->first();
        if (!$record) {
            return back()->with('error', 'Record not found.');
        }
        $columns = array_keys((array) $record);
        $skip = ['id', 'created_at', 'updated_at', '_table', '_id', '_token'];
        $updates = [];
        foreach ($columns as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            if ($request->has($col)) {
                $updates[$col] = $request->input($col);
            }
        }
        if (!empty($updates)) {
            DB::table($tableName)->where('id', $id)->update($updates);
        }
        $type = $request->input('_type', '');
        return redirect()->route('admin.data-master.index', ['type' => $type])->with('success', 'Record updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $allowedTables = $this->getAllowedTablesForCampaign($campaignConfig);
        $tableName = $request->input('_table');
        $id = (int) $request->input('_id');
        if (!$tableName || !in_array($tableName, $allowedTables, true)) {
            return back()->with('error', 'Invalid table.');
        }
        DB::table($tableName)->where('id', $id)->delete();
        $type = $request->input('_type', '');
        return redirect()->route('admin.data-master.index', ['type' => $type])->with('success', 'Record deleted.');
    }

    /** @return list<string> */
    private function getAllowedTablesForCampaign(array $campaignConfig): array
    {
        $allowed = [];
        foreach ($campaignConfig['forms'] ?? [] as $formConfig) {
            $t = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
            if ($t !== '') {
                $allowed[] = $t;
            }
        }
        return $allowed;
    }
}
