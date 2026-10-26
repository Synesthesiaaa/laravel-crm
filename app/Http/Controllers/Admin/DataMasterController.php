<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\FormFieldRepositoryInterface;
use App\Events\DashboardDataUpdated;
use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Services\DataMasterService;
use App\Support\FormFieldValidationRules;
use App\Support\PercentageValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class DataMasterController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected DataMasterService $dataMasterService,
        protected FormFieldRepositoryInterface $formFieldRepository,
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
        $editableColumns = $this->editableColumns($campaign, $type, $tableName);

        return view('admin.data_master_edit', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'type' => $type,
            'tableName' => $tableName,
            'record' => $record,
            'columns' => $layout['columns'],
            'headers' => $layout['headers'],
            'percentageColumns' => $percentageColumns,
            'editableColumns' => $editableColumns,
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

        $type = (string) $request->input('_type', '');
        $formConfig = $campaignConfig['forms'][$type] ?? null;
        $configuredTable = is_array($formConfig)
            ? (string) ($formConfig['table_name'] ?? $formConfig['table'] ?? '')
            : '';
        if ($configuredTable === '' || ! hash_equals($configuredTable, $tableName)) {
            return back()->withErrors(['edit' => 'The selected form does not match this record.'])->withInput();
        }

        $editableColumns = $this->editableColumns($campaign, $type, $tableName);
        $allowedInput = ['_table', '_id', '_type', '_token', '_method'];
        $unexpected = array_diff(array_keys($request->all()), [...$allowedInput, ...$editableColumns]);
        if ($unexpected !== []) {
            return back()->withErrors(['edit' => 'The request includes fields that are not editable.'])->withInput();
        }

        $fieldMap = $this->formFieldRepository->getFieldsForForm($campaign, $type)
            ->keyBy('field_name');
        $input = [];
        $rules = [];
        foreach ($editableColumns as $column) {
            if (! $request->exists($column)) {
                continue;
            }

            $field = $fieldMap->get($column);
            if ($field === null) {
                continue;
            }

            $value = $request->input($column);
            if ($field->field_type === 'multiselect') {
                $value = $this->normalizeMultiselectInput($value);
            } elseif ($field->field_type === 'percentage') {
                $value = $this->dataMasterService->storesPercentageAsNumeric($tableName, $column)
                    ? PercentageValue::numeric($value)
                    : PercentageValue::normalize($value);
            }

            $input[$column] = $value;
            $rules += FormFieldValidationRules::for($field);
        }

        $validated = Validator::make($input, $rules)->validate();
        $updates = [];
        foreach ($validated as $column => $value) {
            $field = $fieldMap->get($column);
            $updates[$column] = $field?->field_type === 'multiselect'
                ? json_encode(array_values((array) $value), JSON_THROW_ON_ERROR)
                : $value;
        }

        if ($updates === []) {
            return back()->withErrors(['edit' => 'Select at least one editable field to update.'])->withInput();
        }

        if ($this->dataMasterService->updateRecord($tableName, $id, $updates, $allowedTables)) {
            event(new DashboardDataUpdated($campaign, $type, $id, 'updated'));
        }

        return redirect()->route('admin.data-master.index', ['type' => $type])->with('success', 'Record updated.');
    }

    /** @return list<string> */
    private function editableColumns(string $campaign, string $type, string $tableName): array
    {
        return $this->formFieldRepository->getFieldsForForm($campaign, $type)
            ->filter(fn ($field): bool => ! in_array((string) $field->field_name, [
                'id', 'date', 'request_id', 'agent', 'lead_id', 'phone_number', 'created_at', 'updated_at',
            ], true) && Schema::hasColumn($tableName, (string) $field->field_name))
            ->pluck('field_name')
            ->values()
            ->all();
    }

    private function normalizeMultiselectInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: []), static fn (string $item): bool => $item !== ''));
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
