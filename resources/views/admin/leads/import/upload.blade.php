@extends('layouts.app')

@section('title', 'Import Leads')
@section('header-icon')<x-icon name="arrow-up-tray" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Import Leads')

@section('content')
<x-page-header title="Import Leads"
    :description="'Upload CSV or XLSX for list: ' . $list->name"
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Lead Lists' => route('admin.leads.lists.index', ['campaign' => $list->campaign_code]),
        $list->name => route('admin.leads.lists.show', $list),
        'Import' => null,
    ]" />

<x-validation-errors />

<ol class="flex gap-3 text-xs text-[var(--color-on-surface-dim)] mb-6">
    <li class="font-semibold text-[var(--color-primary)]">1. Upload</li>
    <li>&rarr; 2. Map headers</li>
    <li>&rarr; 3. Confirm</li>
</ol>

<div class="md-card">
    <div class="p-6">
        <form method="POST" action="{{ route('admin.leads.import.upload', $list) }}" enctype="multipart/form-data"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="text-xs text-[var(--color-on-surface-dim)]">CSV / XLSX / XLS (max 50MB)</label>
                    <input type="file" name="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="form-input w-full">
                </div>
                <div class="text-xs text-[var(--color-on-surface-dim)]">
                    <p>First row must contain column headers. The next step will let you map columns to lead fields.</p>
                    <p><a href="{{ route('admin.leads.export.template', $list) }}" class="underline">Download a template</a> with the currently configured columns.</p>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="arrow-up-tray" class="w-4 h-4" />
                    <span x-text="submitting ? 'Uploading...' : 'Upload & Continue'">Upload & Continue</span>
                </button>
                <a href="{{ route('admin.leads.lists.show', $list) }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
