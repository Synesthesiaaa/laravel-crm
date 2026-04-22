@extends('layouts.app')

@section('title', 'Lead List - ' . $list->name)
@section('header-icon')<x-icon name="queue-list" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Lead List')

@section('content')
<x-page-header :title="$list->name" :description="'Campaign: ' . $list->campaign_code . ' - ' . ($list->active ? 'Active' : 'Disabled')"
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Lead Lists' => route('admin.leads.lists.index', ['campaign' => $list->campaign_code]),
        $list->name => null,
    ]" />

<x-validation-errors />

@if(session('lead_import_run_id'))
    @php
        $ilpRunId = session('lead_import_run_id');
        $ilpEstimated = (int) session('lead_import_estimated_rows', 0);
        $ilpPollUrl = route('admin.leads.import.progress', ['list' => $list, 'runId' => $ilpRunId]);
    @endphp
    <div class="md-card mb-6 border border-[var(--color-primary)]/25 overflow-hidden"
         x-data="leadImportProgress({ pollUrl: @json($ilpPollUrl), estimatedRows: {{ $ilpEstimated }} })"
         x-init="init()">
        <div class="px-4 py-3 border-b border-[var(--color-border)] flex flex-wrap items-center justify-between gap-2 bg-[var(--color-surface-container-low)]">
            <div class="flex items-center gap-2">
                <span class="inline-block size-2 rounded-full bg-[var(--color-primary)] animate-pulse" x-show="state && ['queued','processing'].includes(state.status)"></span>
                <h3 class="text-sm font-semibold">Lead import in progress</h3>
            </div>
            <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="statusLabel()">Starting…</span>
        </div>
        <div class="p-4 space-y-4">
            <div>
                <div class="flex justify-between text-xs text-[var(--color-on-surface-dim)] mb-1">
                    <span>Progress</span>
                    <span x-show="percentDisplay() != null"><span x-text="percentDisplay()"></span>%</span>
                    <span x-show="isIndeterminate()">Estimating…</span>
                </div>
                <div class="h-2.5 rounded-full bg-[var(--color-border)] overflow-hidden">
                    <template x-if="percentDisplay() != null">
                        <div class="h-full rounded-full bg-[var(--color-primary)] transition-[width] duration-500 ease-out"
                             :style="'width:' + Math.min(100, percentDisplay()) + '%'"></div>
                    </template>
                    <template x-if="isIndeterminate()">
                        <div class="h-full w-1/3 rounded-full bg-[var(--color-primary)]/80 animate-pulse"></div>
                    </template>
                </div>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-1" x-show="state && state.estimated_rows > 0">
                    <span x-text="state.rows_processed ?? 0"></span> of <span x-text="state.estimated_rows"></span> file rows touched (approx.; chunks may overlap skips).
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center" x-show="state && state.status !== 'unknown'">
                <div class="rounded-lg bg-[var(--color-surface-container)] p-2">
                    <div class="text-lg font-semibold" x-text="state?.inserted ?? '—'">—</div>
                    <div class="text-[10px] uppercase tracking-wide text-[var(--color-on-surface-dim)]">Inserted</div>
                </div>
                <div class="rounded-lg bg-[var(--color-surface-container)] p-2">
                    <div class="text-lg font-semibold" x-text="state?.updated ?? '—'">—</div>
                    <div class="text-[10px] uppercase tracking-wide text-[var(--color-on-surface-dim)]">Updated</div>
                </div>
                <div class="rounded-lg bg-[var(--color-surface-container)] p-2">
                    <div class="text-lg font-semibold" x-text="state?.skipped ?? '—'">—</div>
                    <div class="text-[10px] uppercase tracking-wide text-[var(--color-on-surface-dim)]">Skipped</div>
                </div>
                <div class="rounded-lg bg-[var(--color-surface-container)] p-2">
                    <div class="text-lg font-semibold" x-text="state?.failed_chunks ?? '0'">0</div>
                    <div class="text-[10px] uppercase tracking-wide text-[var(--color-on-surface-dim)]">Chunk errors</div>
                </div>
            </div>

            <div x-show="state && state.recent && state.recent.length">
                <div class="text-xs font-medium text-[var(--color-on-surface-dim)] mb-2">Latest rows in this import</div>
                <ul class="text-sm space-y-1 max-h-40 overflow-y-auto">
                    <template x-for="(row, idx) in [...(state?.recent || [])].reverse()" :key="(row.phone || '') + '-' + idx">
                        <li class="flex justify-between gap-2 border-b border-[var(--color-border)]/60 py-1 last:border-0">
                            <span class="font-mono text-xs truncate" x-text="row.phone"></span>
                            <span class="text-xs text-[var(--color-on-surface-dim)] truncate" x-text="row.name || '—'"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div x-show="state && state.status === 'failed'" class="rounded-lg bg-red-500/10 text-red-200 text-sm p-3" x-cloak>
                <strong class="block mb-1">Import stopped</strong>
                <span x-text="state.message || 'Unknown error'"></span>
            </div>

            <div x-show="state && state.status === 'unknown'" class="text-xs text-[var(--color-on-surface-dim)]">
                <span x-text="state.message || ''"></span>
            </div>

            <p x-show="error" class="text-xs text-amber-400" x-text="error"></p>

            <p class="text-xs text-[var(--color-on-surface-dim)]" x-show="state && state.status === 'completed'">
                Refreshing page to update totals…
            </p>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="md-card p-4">
        <div class="text-xs text-[var(--color-on-surface-dim)]">Total Leads</div>
        <div class="text-2xl font-semibold">{{ number_format($list->leads_count ?? 0) }}</div>
    </div>
    <div class="md-card p-4">
        <div class="text-xs text-[var(--color-on-surface-dim)]">Status</div>
        <div class="text-2xl font-semibold">
            <x-badge :type="$list->active ? 'active' : 'inactive'">
                {{ $list->active ? 'Enabled' : 'Disabled' }}
            </x-badge>
        </div>
    </div>
    <div class="md-card p-4">
        <div class="text-xs text-[var(--color-on-surface-dim)]">Last Reset</div>
        <div class="text-sm">{{ optional($list->reset_time)->format('Y-m-d H:i') ?? 'Never' }}</div>
    </div>
</div>

{{-- Quick actions --}}
<div class="md-card mb-6">
    <div class="p-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.leads.leads.index', $list) }}" class="btn-primary text-sm">
            <x-icon name="list-bullet" class="w-4 h-4" /> View Leads
        </a>
        <a href="{{ route('admin.leads.leads.create', $list) }}" class="btn-secondary text-sm">
            <x-icon name="plus" class="w-4 h-4" /> Add Lead
        </a>
        <a href="{{ route('admin.leads.import.form', $list) }}" class="btn-secondary text-sm">
            <x-icon name="arrow-up-tray" class="w-4 h-4" /> Import
        </a>
        <a href="{{ route('admin.leads.export.download', ['list' => $list, 'format' => 'xlsx']) }}" class="btn-ghost text-sm">
            <x-icon name="arrow-down-tray" class="w-4 h-4" /> Export XLSX
        </a>
        <a href="{{ route('admin.leads.export.download', ['list' => $list, 'format' => 'csv']) }}" class="btn-ghost text-sm">
            <x-icon name="arrow-down-tray" class="w-4 h-4" /> Export CSV
        </a>
        <a href="{{ route('admin.leads.export.template', $list) }}" class="btn-ghost text-sm">
            <x-icon name="document-text" class="w-4 h-4" /> Download Template
        </a>
        <form method="POST" action="{{ route('admin.leads.lists.load-hopper', $list) }}" class="inline">
            @csrf
            <button type="submit" class="btn-secondary text-sm" @if(!$list->active) disabled @endif>
                <x-icon name="rocket-launch" class="w-4 h-4" /> Load Hopper
            </button>
        </form>
        <form method="POST" action="{{ route('admin.leads.lists.toggle', $list) }}" class="inline">
            @csrf
            <input type="hidden" name="active" value="{{ $list->active ? 0 : 1 }}">
            <button type="submit" class="{{ $list->active ? 'btn-warning' : 'btn-primary' }} text-sm">
                <x-icon :name="$list->active ? 'pause' : 'play'" class="w-4 h-4" />
                {{ $list->active ? 'Disable List' : 'Enable List' }}
            </button>
        </form>
    </div>
</div>

{{-- Edit --}}
<div class="md-card">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold">Edit List</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.leads.lists.update', $list) }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-form.input name="name" label="Name" :value="old('name', $list->name)" required />
                <x-form.input name="description" label="Description" :value="old('description', $list->description)" />
                <x-form.input name="display_order" type="number" label="Display Order" :value="old('display_order', $list->display_order)" />
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="check" class="w-4 h-4" /> Update
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
