@extends('layouts.app')

@section('title', 'Map Import Headers')
@section('header-icon')<x-icon name="arrow-up-tray" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Map Headers')

@section('content')
<x-page-header title="Map Headers"
    :description="'File has ' . number_format($stash['rows'] ?? 0) . ' rows.'"
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Lead Lists' => route('admin.leads.lists.index', ['campaign' => $list->campaign_code]),
        $list->name => route('admin.leads.lists.show', $list),
        'Import' => route('admin.leads.import.form', $list),
        'Mapping' => null,
    ]" />

<x-validation-errors />

<ol class="flex gap-3 text-xs text-[var(--color-on-surface-dim)] mb-6">
    <li>1. Upload</li>
    <li class="font-semibold text-[var(--color-primary)]">&rarr; 2. Map headers</li>
    <li>&rarr; 3. Confirm</li>
</ol>

<form method="POST" action="{{ route('admin.leads.import.confirm', $list) }}" x-data="{ submitting: false }" @submit="submitting = true">
    @csrf

    <div class="md-card mb-4">
        <div class="px-6 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold">Column Mapping</h3>
            <p class="text-xs text-[var(--color-on-surface-dim)]">At least one column must map to <code>phone_number</code>.</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($stash['headers'] as $header)
                    @php
                        $normalized = \Illuminate\Support\Str::snake(strtolower(trim($header)));
                        $suggested = $fields->firstWhere('field_key', $normalized);
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="flex-1 text-sm font-mono bg-[var(--color-surface-variant)] px-2 py-1 rounded">
                            {{ $header ?: '(empty)' }}
                        </div>
                        <span class="text-xs text-[var(--color-on-surface-dim)]">&rarr;</span>
                        <select name="mapping[{{ $header }}]" class="form-select flex-1">
                            <option value="__skip__">-- Skip this column --</option>
                            @foreach($fields as $field)
                                <option value="{{ $field->field_key }}" @selected($suggested && $suggested->field_key === $field->field_key)>
                                    {{ $field->field_label }} ({{ $field->field_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="md-card mb-4">
        <div class="px-6 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold">Options</h3>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-xs text-[var(--color-on-surface-dim)]">Deduplicate by</label>
                <select name="dedupe" class="form-select w-full">
                    <option value="">None (allow duplicates)</option>
                    <option value="phone_number">Phone Number</option>
                    <option value="vendor_lead_code">Vendor Lead Code</option>
                </select>
            </div>
            <div class="flex items-center">
                <x-form.checkbox name="update_existing" label="Update existing on duplicate" />
            </div>
        </div>
    </div>

    @if(! empty($stash['preview']))
        <div class="md-card mb-4">
            <div class="px-6 py-4 border-b border-[var(--color-border)]">
                <h3 class="text-sm font-semibold">Preview (first {{ count($stash['preview']) }} rows)</h3>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            @foreach($stash['headers'] as $h)
                                <th class="text-left px-2 py-1 border-b border-[var(--color-border)]">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stash['preview'] as $row)
                            <tr>
                                @foreach($stash['headers'] as $i => $h)
                                    <td class="px-2 py-1 border-b border-[var(--color-border)]">{{ $row[$i] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="flex gap-2">
        <button type="submit" class="btn-primary" :disabled="submitting">
            <x-icon name="check" class="w-4 h-4" />
            <span x-text="submitting ? 'Queuing...' : 'Queue Import'">Queue Import</span>
        </button>
        <a href="{{ route('admin.leads.import.form', $list) }}" class="btn-ghost">Start Over</a>
    </div>
</form>
@endsection
