@extends('layouts.app')

@section('title', 'Agent Call Records - Admin')
@section('header-icon')<x-icon name="phone" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Agent Call Records')

@section('content')
<x-page-header title="Agent Call Records"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Agent Call Records' => null]" />

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.agent-records.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="agent" label="Agent" :value="request('agent')" class="w-40" />
            <x-form.select name="disposition" label="Disposition"
                :options="$dispositionCodes->pluck('label','code')->prepend('All dispositions','')->all()"
                :selected="request('disposition')" :empty="false" />
            <x-form.select name="source" label="Source"
                :options="[
                    '' => 'All sources',
                    'agent' => 'Agent',
                    'vicidial_webhook' => 'ViciDial webhook',
                    'vicidial_poll' => 'ViciDial poll',
                ]"
                :selected="request('source')" :empty="false" />
            <x-form.input name="from_date" type="date" label="From" :value="request('from_date')" />
            <x-form.input name="to_date" type="date" label="To" :value="request('to_date')" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
                    <a href="{{ route('admin.agent-records.index') }}" class="btn-ghost">Clear</a>
                </div>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.agent-records.export') }}" class="mt-3 inline">
            @csrf
            <input type="hidden" name="from_date" value="{{ request('from_date') }}" />
            <input type="hidden" name="to_date" value="{{ request('to_date') }}" />
            <button type="submit" class="btn-secondary text-sm"><x-icon name="arrow-down-tray" class="w-4 h-4" /> Export CSV</button>
        </form>
    </div>
</div>

<x-table.index caption="Agent call & disposition records">
    <x-table.head :columns="[
        ['label' => 'Called At'],
        ['label' => 'Phone'],
        ['label' => 'Lead PK'],
        ['label' => 'Agent'],
        ['label' => 'Disposition'],
        ['label' => 'Source'],
        ['label' => 'Edited'],
        ['label' => '', 'align' => 'right'],
    ]" />
    @if($records->isEmpty())
        <x-table.empty :colspan="8" message="No records." />
    @else
    <tbody>
        @foreach($records as $r)
            <tr>
                <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">{{ $r->called_at?->format('Y-m-d H:i:s') }}</td>
                <td class="font-mono text-sm">{{ $r->phone_number ?? '—' }}</td>
                <td class="font-mono text-sm">{{ $r->lead_pk ?? '—' }}</td>
                <td>{{ $r->agent }}</td>
                <td>
                    <x-badge type="info">{{ $r->disposition_label ?? $r->disposition_code }}</x-badge>
                </td>
                <td class="text-xs text-[var(--color-on-surface-muted)]">{{ $r->disposition_source }}</td>
                <td class="text-xs">{{ $r->last_edited_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td class="text-right">
                    <a href="{{ route('admin.agent-records.edit', $r) }}" class="link-primary text-sm">Edit</a>
                </td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>
<x-table.pagination :paginator="$records" />
@endsection
