<?php

namespace App\Services;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AgentDataMasterService
{
    public function __construct(private DataMasterService $dataMaster) {}

    public function page(Form $form, User $viewer, bool $ownOnly, array $filters): array
    {
        $table = $form->table_name;
        abort_unless(is_string($table) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) === 1 && Schema::hasTable($table), 503, 'Form records are unavailable. Ask an administrator to check form storage.');
        $available = Schema::getColumnListing($table);
        abort_unless(in_array('id', $available, true), 503, 'Form records are unavailable. Ask an administrator to check form storage.');
        $layout = $this->dataMaster->getColumnLayout($form->campaign_code, $form->form_code, $available);
        $columns = array_values(array_filter($layout['columns'], fn ($column) => in_array($column, $available, true) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column) === 1));
        $columnFilters = $filters['column_filters'] ?? [];
        if (array_diff(array_keys($columnFilters), $columns) !== []) {
            throw ValidationException::withMessages(['column_filters' => 'Choose only columns shown in this form.']);
        }

        $query = DB::table($table.' as records')->select(array_map(fn ($column) => 'records.'.$column, $columns))->orderByDesc('records.id');
        $shared = Form::withTrashed()->where('table_name', $table)->where('id', '!=', $form->id)->exists();
        if ($ownOnly || $shared) {
            $query->whereExists(function (Builder $history) use ($form, $viewer, $ownOnly): void {
                $history->selectRaw('1')->from('crm_call_history as submission')
                    ->whereColumn('submission.record_id', 'records.id')
                    ->where('submission.campaign_code', $form->campaign_code)
                    ->where('submission.form_type', $form->form_code);
                if ($ownOnly) {
                    $history->where('submission.user_id', $viewer->id);
                }
            });
        }

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $query) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $query->orWhere('records.'.$column, 'like', '%'.$search.'%');
                }
            });
        }
        foreach ($columnFilters as $column => $value) {
            if (trim($value ?? '') !== '') {
                $query->where('records.'.$column, 'like', '%'.trim($value).'%');
            }
        }
        $dateColumn = in_array('created_at', $available, true) ? 'created_at' : (in_array('date', $available, true) ? 'date' : null);
        if ($dateColumn === null && (! empty($filters['date_from']) || ! empty($filters['date_to']))) {
            throw ValidationException::withMessages(['date_from' => 'This form does not have a submission date column.']);
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $query->whereDate('records.'.$dateColumn, $operator, $filters[$key]);
            }
        }

        return [
            'records' => $query->paginate(20)->appends(array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== [])),
            'columns' => $columns,
            'headers' => $layout['headers'],
            'percentageColumns' => $this->dataMaster->getPercentageColumns($form->campaign_code, $form->form_code),
            'dataMasterService' => $this->dataMaster,
        ];
    }
}
