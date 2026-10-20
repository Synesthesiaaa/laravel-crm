@extends('layouts.app')

@section('title', 'Lead Hopper')
@section('header-icon')<x-icon name="list-bullet" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Lead Hopper')

@section('content')
<x-page-header title="Lead Hopper" description="Monitor predictive lead availability and queue CSV imports."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Lead Hopper' => null]" />

<x-validation-errors />

@if(session('success'))
    <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
@endif
@if(session('error'))
    <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
@endif

@if(! $schemaReady)
    <x-alert type="warning" title="Schema not ready">
        Lead hopper tables are not available yet. Run migrations before importing leads.
    </x-alert>
@else
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @foreach(['pending' => 'Pending', 'assigned' => 'Assigned', 'completed' => 'Completed', 'failed' => 'Failed'] as $status => $label)
            <x-stat-card :label="$label" :value="number_format($statusCounts[$status] ?? 0)" icon="list-bullet" color="info" />
        @endforeach
    </div>

    <div class="md-card mb-6">
        <div class="px-6 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Import Leads</h3>
        </div>
        <div class="p-6">
            @if(empty($forms))
                <x-alert type="warning">No forms are configured for the current campaign.</x-alert>
            @else
                <form method="POST" action="{{ route('admin.lead-hopper.import') }}" enctype="multipart/form-data"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div class="form-field">
                            <label class="form-label" for="form_type">Form Type</label>
                            <select id="form_type" name="form_type" class="form-select" required>
                                @foreach($forms as $formCode => $formConfig)
                                    <option value="{{ $formCode }}">{{ $formConfig['name'] ?? $formCode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="csv_file">CSV File</label>
                            <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv,text/plain" class="form-input" required>
                        </div>
                        <button type="submit" class="btn-primary" :disabled="submitting">
                            <x-icon name="arrow-down-tray" class="w-4 h-4" />
                            <span x-text="submitting ? 'Queueing...' : 'Queue Import'">Queue Import</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div>
            <x-table.index caption="Recent hopper leads">
                <x-table.head :columns="[
                    ['label' => 'Lead'],
                    ['label' => 'Phone'],
                    ['label' => 'Status'],
                    ['label' => 'Attempts', 'align' => 'right'],
                ]" />
                @if($recentLeads->isNotEmpty())
                    <tbody>
                    @foreach($recentLeads as $lead)
                        <tr>
                            <td>
                                <div class="font-medium text-[var(--color-on-surface)]">{{ $lead->client_name ?: '—' }}</div>
                                <div class="text-xs text-[var(--color-on-surface-dim)]">{{ $lead->lead_id ?: 'No lead id' }}</div>
                            </td>
                            <td class="font-mono text-sm">{{ $lead->phone_number }}</td>
                            <td><x-badge :type="$lead->status === 'pending' ? 'active' : ($lead->status === 'failed' ? 'error' : 'pending')">{{ $lead->status }}</x-badge></td>
                            <td class="text-right tabular-nums">{{ number_format((int) $lead->attempt_count) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                @else
                    <x-table.empty :colspan="4" message="No hopper leads for this campaign." />
                @endif
            </x-table.index>
        </div>

        <div>
            <x-table.index caption="Recent lead imports">
                <x-table.head :columns="[
                    ['label' => 'File'],
                    ['label' => 'Status'],
                    ['label' => 'Imported', 'align' => 'right'],
                    ['label' => 'Uploaded'],
                ]" />
                @if($imports->isNotEmpty())
                    <tbody>
                    @foreach($imports as $import)
                        <tr>
                            <td>
                                <div class="font-medium text-[var(--color-on-surface)]">{{ $import->original_filename ?: basename($import->file_path) }}</div>
                                <div class="text-xs text-[var(--color-on-surface-dim)]">{{ $import->form_type }}</div>
                                @if($import->error_summary)
                                    <div class="text-xs text-[var(--color-danger)] mt-1">{{ $import->error_summary }}</div>
                                @endif
                            </td>
                            <td><x-badge :type="$import->status === 'completed' ? 'active' : ($import->status === 'failed' ? 'error' : 'pending')">{{ $import->status }}</x-badge></td>
                            <td class="text-right tabular-nums">{{ number_format($import->imported_count) }}</td>
                            <td class="text-xs text-[var(--color-on-surface-dim)]">
                                {{ $import->created_at?->format('Y-m-d H:i') }}<br>
                                {{ $import->uploadedBy?->username ?? 'system' }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                @else
                    <x-table.empty :colspan="4" message="No imports queued yet." />
                @endif
            </x-table.index>
        </div>
    </div>
@endif
@endsection
