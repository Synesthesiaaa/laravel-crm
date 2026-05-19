@extends('layouts.app')

@section('title', 'Capture Records - Admin')
@section('header-icon')<x-icon name="clipboard-document-check" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Capture Records')

@section('content')
<x-page-header title="Capture Records"
    description="View, update, delete, and export agent capture details."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Capture Records' => null]" />

<x-validation-errors />

@if(session('success'))
    <x-alert type="success" class="mb-4">
        {{ session('success') }}
    </x-alert>
@endif
@if(session('error'))
    <x-alert type="error" class="mb-4">
        {{ session('error') }}
    </x-alert>
@endif

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.capture-records.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="agent" label="Agent" :value="$filters['agent']" />
            <x-form.input name="lead_id" label="Lead ID" :value="$filters['lead_id']" />
            <x-form.input name="phone" label="Phone" :value="$filters['phone']" />
            <x-form.input name="from_date" type="date" label="From" :value="$filters['from_date']" />
            <x-form.input name="to_date" type="date" label="To" :value="$filters['to_date']" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">
                        <x-icon name="funnel" class="w-4 h-4" />
                        Filter
                    </button>
                    <a href="{{ route('admin.capture-records.index') }}" class="btn-ghost">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mb-4 flex justify-end">
    <form method="POST" action="{{ route('admin.capture-records.export') }}">
        @csrf
        <input type="hidden" name="agent" value="{{ $filters['agent'] }}">
        <input type="hidden" name="lead_id" value="{{ $filters['lead_id'] }}">
        <input type="hidden" name="phone" value="{{ $filters['phone'] }}">
        <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
        <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
        <button type="submit" class="btn-secondary">
            <x-icon name="arrow-down-tray" class="w-4 h-4" />
            Export CSV
        </button>
    </form>
</div>

<x-table.index caption="Capture records">
    <thead>
        <tr>
            <th>Submitted At</th>
            <th>Agent</th>
            <th>Lead ID</th>
            <th>Phone</th>
            @foreach($fields as $field)
                <th>{{ $field->field_label }}</th>
            @endforeach
            <th style="text-align: right">Actions</th>
        </tr>
    </thead>
    @if($records->isEmpty())
        <x-table.empty :colspan="5 + $fields->count()" message="No capture records found." />
    @else
    <tbody>
        @foreach($records as $record)
            @php($captureData = is_array($record->capture_data) ? $record->capture_data : [])
            <tr>
                <td class="whitespace-nowrap text-sm text-[var(--color-on-surface-muted)]">
                    {{ $record->created_at?->format('Y-m-d H:i:s') }}
                </td>
                <td>{{ $record->agent }}</td>
                <td class="font-mono text-sm">{{ $record->lead_id ?? '—' }}</td>
                <td class="font-mono text-sm">{{ $record->phone_number ?? '—' }}</td>
                @foreach($fields as $field)
                    <td>{{ $captureData[$field->field_key] ?? '—' }}</td>
                @endforeach
                <td>
                    <div class="table-actions" x-data="{ async del(form) {
                        const ok = await Alpine.store('confirm').ask('Delete capture record?', 'This record will be permanently removed.');
                        if (ok) form.submit();
                    }}">
                        <a href="{{ route('admin.capture-records.edit', ['record' => $record->id]) }}"
                           class="btn-secondary text-xs px-2 py-1">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            Edit
                        </a>
                        <form method="POST" action="{{ route('admin.capture-records.destroy') }}" x-ref="delFormCR{{ $record->id }}">
                            @csrf
                            <input type="hidden" name="id" value="{{ $record->id }}">
                            <button type="button" class="btn-danger text-xs px-2 py-1"
                                    @click="del($refs['delFormCR{{ $record->id }}'])">
                                <x-icon name="trash" class="w-3.5 h-3.5" />
                                Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>
<x-table.pagination :paginator="$records" />
@endsection
