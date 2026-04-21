<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Services\DataMasterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataMasterController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DataMasterService $dataMasterService,
    ) {}

    public function index(Request $request): View
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $forms = $campaignConfig['forms'] ?? [];
        $type = $request->query('type', array_key_first($forms) ?: '');
        if ($type !== '' && ! isset($forms[$type])) {
            $type = array_key_first($forms) ?: '';
        }
        $tableName = $forms[$type]['table_name'] ?? $forms[$type]['table'] ?? '';
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);
        $records = $this->dataMasterService->getRecords($tableName, $allowedTables);
        $columns = [];
        $first = $records->first();
        if ($first) {
            $columns = array_keys((array) $first);
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
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);

        if (! $this->dataMasterService->isTableAllowed($tableName, $allowedTables)) {
            return redirect()->route('admin.data-master.index')->with('error', 'Invalid table.');
        }

        $record = $this->dataMasterService->getRecord($tableName, $id, $allowedTables);
        if (! $record) {
            return redirect()->route('admin.data-master.index', ['type' => $type])->with('error', 'Record not found.');
        }

        return view('admin.data_master_edit', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'type' => $type,
            'tableName' => $tableName,
            'record' => $record,
            'columns' => array_keys((array) $record),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);
        $tableName = (string) $request->input('_table', '');
        $id = (int) $request->input('_id');

        if (! $this->dataMasterService->isTableAllowed($tableName, $allowedTables)) {
            return back()->with('error', 'Invalid table.');
        }

        $record = $this->dataMasterService->getRecord($tableName, $id, $allowedTables);
        if (! $record) {
            return back()->with('error', 'Record not found.');
        }

        $skip = ['id', 'created_at', 'updated_at', '_table', '_id', '_token'];
        $columns = array_keys((array) $record);
        $updates = [];
        foreach ($columns as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            if ($request->has($col)) {
                $updates[$col] = $request->input($col);
            }
        }

        $this->dataMasterService->updateRecord($tableName, $id, $updates, $allowedTables);
        $type = (string) $request->input('_type', '');

        return redirect()->route('admin.data-master.index', ['type' => $type])->with('success', 'Record updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $campaign = $request->session()->get('campaign', 'mbsales');
        $campaignConfig = $this->campaignService->getCampaign($campaign) ?? ['forms' => []];
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);
        $tableName = (string) $request->input('_table', '');
        $id = (int) $request->input('_id');

        if (! $this->dataMasterService->isTableAllowed($tableName, $allowedTables)) {
            return back()->with('error', 'Invalid table.');
        }

        $this->dataMasterService->deleteRecord($tableName, $id, $allowedTables);
        $type = (string) $request->input('_type', '');

        return redirect()->route('admin.data-master.index', ['type' => $type])->with('success', 'Record deleted.');
    }
}
