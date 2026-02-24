@extends('layouts.app')

@section('title', 'Data Extraction - Admin')
@section('header-icon')<x-icon name="arrow-down-tray" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Data Extraction')

@section('content')
<x-page-header title="Data Extraction" description="Export form submission data to CSV."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Extraction' => null]" />

<x-validation-errors />

<div class="md-card max-w-xl">
    <div class="p-6">
        <form method="POST" action="{{ route('admin.extraction.export') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="space-y-4">
                <x-form.input name="start_date" type="date" label="Start Date" required />
                <x-form.input name="end_date"   type="date" label="End Date"   required />
                <x-form.select name="data_type" label="Data Type"
                    :options="collect($forms)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->prepend('All forms','all')->all()"
                    :empty="false" />
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="arrow-down-tray" class="w-4 h-4" />
                    <span x-text="submitting ? 'Exporting...' : 'Export CSV'">Export CSV</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
