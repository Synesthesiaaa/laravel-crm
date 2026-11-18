@extends('layouts.app')

@section('title', 'Data Master')
@section('header-icon')<x-icon name="list-bullet" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Data Master')

@section('content')
<x-page-header title="Data Master" :description="$campaignName.' · Read-only campaign records'"
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Data Master' => null]" />

<x-validation-errors />

<p class="text-sm mb-4">
    <strong>{{ $ownOnly ? 'Your submissions only' : 'All campaign submissions' }}</strong>
    <span class="text-[var(--color-on-surface-dim)]"> · Access and available forms are set by your Super Admin.</span>
</p>

<div class="md-card mb-6 p-4 space-y-4">
    <form method="GET" action="{{ route('agent.data-master.index') }}" data-soft-nav class="flex flex-wrap items-end gap-4">
        <x-form.select name="type" label="Form" :options="$forms->pluck('name', 'form_code')->all()" :selected="$type" :empty="false" />
        <button type="submit" class="btn-secondary">Load form</button>
    </form>
<form method="GET" action="{{ route('agent.data-master.index') }}" data-soft-nav class="space-y-4">
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-form.input name="search" type="search" label="Search records" :value="$filters['search'] ?? ''" placeholder="Search visible columns" maxlength="100" />
        <x-form.input name="date_from" type="date" label="Submitted from" :value="$filters['date_from'] ?? ''" />
        <x-form.input name="date_to" type="date" label="Submitted through" :value="$filters['date_to'] ?? ''" />
    </div>
    <details @if(collect($filters['column_filters'] ?? [])->filter(fn($value) => trim($value ?? '') !== '')->isNotEmpty()) open @endif>
        <summary class="text-sm font-medium cursor-pointer py-2">Column filters</summary>
        <p class="form-help mt-2 mb-4">Match text within individual columns. All filters apply together.</p>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($columns as $column)
                <x-form.input :name="'column_filters['.$column.']'" :label="$headers[$column] ?? $column"
                    :value="$filters['column_filters'][$column] ?? ''" placeholder="Contains…" maxlength="100" />
            @endforeach
        </div>
    </details>
    <div class="form-actions-bottom">
        <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Apply filters</button>
        <a href="{{ route('agent.data-master.index', ['type' => $type]) }}" class="btn-ghost" data-soft-nav-clear>Clear filters</a>
    </div>
</form>
</div>

<div class="data-master-desktop-table">
    <x-table.index caption="Read-only Data Master records">
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th scope="col">{{ $headers[$column] ?? $column }}</th>
                @endforeach
            </tr>
        </thead>
        @if($records->isEmpty())
            <x-table.empty :colspan="count($columns)" message="No submissions match this view. Try clearing filters or selecting another permitted form." />
        @else
            <tbody>
                @foreach($records as $row)
                    <tr>
                        @foreach($columns as $column)
                            <td>{{ $dataMasterService->formatValue($column, $row->$column ?? '', $percentageColumns) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        @endif
        <x-slot:footer><x-table.pagination :paginator="$records" /></x-slot:footer>
    </x-table.index>
</div>
@endsection
