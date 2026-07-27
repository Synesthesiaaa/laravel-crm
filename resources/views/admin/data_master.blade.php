@extends('layouts.app')

@section('title', 'Data Master - Admin')
@section('header-icon')<x-icon name="list-bullet" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Data Master')

@section('content')
<x-page-header title="Data Master" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Data Master' => null]" />

<x-validation-errors />

@if(empty($forms))
    <x-alert type="warning" class="mb-4">No active forms are configured for this campaign.</x-alert>
@endif

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.data-master.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.select name="type" label="Form Type"
                :options="collect($forms)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->all()"
                :selected="$type" :empty="false" />
            <div class="form-actions-bottom">
                <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Load</button>
            </div>
        </form>
    </div>
</div>

@if($tableName)
<div class="data-master-responsive">
<x-table.index caption="Data master records" class="data-master-desktop-table">
    <thead>
        <tr>
            @foreach($columns as $col)
                <th>{{ $headers[$col] ?? $col }}</th>
            @endforeach
            <th style="text-align: right">Actions</th>
        </tr>
    </thead>
    @if($records->isEmpty())
        <x-table.empty :colspan="count($columns) + 1" message="No records found." />
    @else
    <tbody>
        @foreach($records as $row)
            <tr>
                @foreach($columns as $col)
                    <td>{{ $dataMasterService->formatValue($col, is_object($row) ? ($row->$col ?? '') : ($row[$col] ?? ''), $percentageColumns ?? []) }}</td>
                @endforeach
                <td>
                    @include('admin.partials.data_master_actions', [
                        'row' => $row,
                        'tableName' => $tableName,
                        'type' => $type,
                    ])
                </td>
            </tr>
        @endforeach
    </tbody>
    @endif
    <x-slot:footer>
        <x-table.pagination :paginator="$records" />
    </x-slot:footer>
</x-table.index>
<div class="md-table-wrap data-master-mobile-table" aria-label="Data master records">
    @if($records->isEmpty())
        <div class="table-empty">
            <x-icon name="document-text" class="w-10 h-10 mx-auto mb-2" />
            <p class="font-medium text-sm">No records found.</p>
        </div>
    @else
        <div class="data-master-mobile-list">
            @foreach($records as $row)
                <article class="data-master-mobile-card">
                    <dl>
                        @foreach($columns as $col)
                            <div class="data-master-mobile-field">
                                <dt>{{ $headers[$col] ?? $col }}</dt>
                                <dd>{{ $dataMasterService->formatValue($col, is_object($row) ? ($row->$col ?? '') : ($row[$col] ?? ''), $percentageColumns ?? []) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @include('admin.partials.data_master_actions', [
                        'row' => $row,
                        'tableName' => $tableName,
                        'type' => $type,
                        'class' => 'data-master-mobile-action-buttons',
                    ])
                </article>
            @endforeach
        </div>
    @endif
    <x-table.pagination :paginator="$records" />
</div>
</div>
@elseif(empty($forms))
<div class="md-card">
    <div class="table-empty py-12">
        <x-icon name="list-bullet" class="w-10 h-10 mx-auto mb-2" />
        <p class="text-sm font-medium">No active forms are configured for this campaign.</p>
    </div>
</div>
@else
<div class="md-card">
    <div class="table-empty py-12">
        <x-icon name="list-bullet" class="w-10 h-10 mx-auto mb-2" />
        <p class="text-sm font-medium">Select a form type to load records.</p>
    </div>
</div>
@endif
@endsection
