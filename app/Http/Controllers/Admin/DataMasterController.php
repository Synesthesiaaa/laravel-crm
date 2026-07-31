<?php

namespace App\Http\Controllers\Admin;

use App\Events\DashboardDataUpdated;
use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Services\DataMasterService;
use App\Support\PercentageValue;
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
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaign = $resolved['code'];
        $campaignConfig = $resolved['config'];
        $forms = $campaignConfig['forms'] ?? [];
        $type = $forms === [] ? '' : (string) $request->query('type', array_key_first($forms) ?: '');
        if ($type !== '' && ! isset($forms[$type])) {
            $type = array_key_first($forms) ?: '';
        }
        $tableName = $forms[$type]['table_name'] ?? $forms[$type]['table'] ?? '';
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100) : '';
        $records = $this->dataMasterService->getRecords(
            $tableName,
            $allowedTables,
            search: $search !== '' ? $search : null,
        );
        $available = null;
        $first = $records->first();
        if ($first) {
            $available = array_keys((array) $first);
        }
        $layout = $this->dataMasterService->getColumnLayout($campaign, $type, $available);
        $percentageColumns = $this->dataMasterService->getPercentageColumns($campaign, $type);

        return view('admin.data_master', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'forms' => $forms,
            'type' => $type,
            'tableName' => $tableName,
            'records' => $records,
            'columns' => $layout['columns'],
            'headers' => $layout['headers'],
            'percentageColumns' => $percentageColumns,
            'search' => $search,
            'dataMasterService' => $this->dataMasterService,
        ]);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaign = $resolved['code'];
        $campaignConfig = $resolved['config'];
        $forms = $campaignConfig['forms'] ?? [];
        $type = $forms === [] ? '' : (string) $request->query('type', array_key_first($forms) ?: '');
        $tableName = $forms[$type]['table_name'] ?? $forms[$type]['table'] ?? '';
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);

        if (! $this->dataMasterService->isTableAllowed($tableName, $allowedTables)) {
            return redirect()->route('admin.data-master.index')->with('error', 'Invalid table.');
        }

        $record = $this->dataMasterService->getRecord($tableName, $id, $allowedTables);
        if (! $record) {
            return redirect()->route('admin.data-master.index', ['type' => $type])->with('error', 'Record not found.');
        }

        $layout = $this->dataMasterService->getColumnLayout($campaign, $type, array_keys((array) $record));
        $percentageColumns = $this->dataMasterService->getPercentageColumns($campaign, $type);

        return view('admin.data_master_edit', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'type' => $type,
            'tableName' => $tableName,
            'record' => $record,
            'columns' => $layout['columns'],
            'headers' => $layout['headers'],
            'percentageColumns' => $percentageColumns,
            'dataMasterService' => $this->dataMasterService,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaign = $resolved['code'];
        $campaignConfig = $resolved['config'];
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
        $type = (string) $request->input('_type', '');
        $percentageColumns = $this->dataMasterService->getPercentageColumns($campaign, $type);
        $updates = [];
        foreach ($columns as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            if ($request->has($col)) {
                if (in_array($col, $percentageColumns, true)) {
                    $updates[$col] = $this->dataMasterService->storesPercentageAsNumeric($tableName, $col)
                        ? PercentageValue::numeric($request->input($col))
                        : PercentageValue::normalize($request->input($col));
                } else {
                    $updates[$col] = $request->input($col);
                }
            }
        }

        if ($this->dataMasterService->updateRecord($tableName, $id, $updates, $allowedTables)) {
            event(new DashboardDataUpdated($campaign, $type, $id, 'updated'));
        }

        return redirect()->route('admin.data-master.index', ['type' => $type])->with('success', 'Record updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $resolved = $this->campaignService->resolveCampaignForRequest($request);
        $campaignConfig = $resolved['config'];
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
