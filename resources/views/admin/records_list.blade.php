@extends('layouts.app')

@section('title', 'Records List - Admin')
@section('header-icon')<x-icon name="table-cells" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Records List')

@section('content')
<x-page-header title="Records List" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Records List' => null]" />

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.records.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="start_date" type="date" label="Start Date" :value="request('start_date')" />
            <x-form.input name="end_date"   type="date" label="End Date"   :value="request('end_date')" />
            <x-form.input name="agent" label="Agent" :value="request('agent')" placeholder="Agent name" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
                    <a href="{{ route('admin.records.index') }}" class="btn-ghost">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Form submission records">
    <x-table.head :columns="[
        ['label' => 'Date'],
        ['label' => 'Form'],
        ['label' => 'Agent'],
        ['label' => 'Phone'],
        ['label' => 'Status'],
    ]" />
    @if($history->isEmpty())
        <x-table.empty :colspan="5" message="No records found." />
    @else
    <tbody>
        @foreach($history as $row)
            <tr>
                <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                <td>{{ $row->form_type }}</td>
                <td>{{ $row->agent }}</td>
                <td class="font-mono text-sm">{{ $row->phone_number ?? '—' }}</td>
                <td><x-badge type="active">{{ $row->status ?? 'RECORDED' }}</x-badge></td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>
<x-table.pagination :paginator="$history" />
@endsection
