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
