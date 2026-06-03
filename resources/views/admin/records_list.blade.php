@extends('layouts.app')

@section('title', 'Records List - Admin')
@section('header-icon')<x-icon name="table-cells" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Records List')

@php
    $statusOptions = [
        'dialing' => 'Dialing',
        'ringing' => 'Ringing',
        'answered' => 'Answered',
        'in_call' => 'In Call',
        'on_hold' => 'On Hold',
        'transferring' => 'Transferring',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'abandoned' => 'Abandoned',
    ];
    $formatDuration = static function ($seconds): string {
        if ($seconds === null) {
            return '-';
        }
        $seconds = (int) $seconds;
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    };
    $statusBadge = static fn (?string $status): string => match ($status) {
        'completed' => 'active',
        'failed', 'abandoned' => 'error',
        'dialing', 'ringing', 'answered', 'in_call', 'on_hold', 'transferring' => 'pending',
        default => 'inactive',
    };
    $tabParams = request()->except('page');
@endphp

@section('content')
<nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}" class="link-primary">Admin</a>
    <span class="mx-1.5">/</span>
    <span class="text-[var(--color-on-surface-muted)]">Records List</span>
</nav>

<div class="md-card mb-4 md-card--static">
    <div class="px-4 pt-4 border-b border-[var(--color-border)]">
        <div class="flex flex-wrap gap-2 overflow-x-auto" role="tablist" aria-label="Records list sections">
            <a href="{{ route('admin.records.index', array_merge($tabParams, ['tab' => 'submissions'])) }}"
               role="tab"
               aria-selected="{{ $activeTab === 'submissions' ? 'true' : 'false' }}"
               id="records-tab-submissions"
               class="px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'submissions' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-dim)] hover:text-[var(--color-on-surface)]' }}">
                Submitted Records
            </a>
            <a href="{{ route('admin.records.index', array_merge($tabParams, ['tab' => 'calls'])) }}"
               role="tab"
               aria-selected="{{ $activeTab === 'calls' ? 'true' : 'false' }}"
               id="records-tab-calls"
               class="px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'calls' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-dim)] hover:text-[var(--color-on-surface)]' }}">
                Call Sessions
            </a>
        </div>
    </div>
    <div class="p-4">
        <form method="GET" action="{{ route('admin.records.index') }}" class="filter-row">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <x-form.input name="start_date" type="date" label="Start Date" :value="request('start_date')" />
            <x-form.input name="end_date" type="date" label="End Date" :value="request('end_date')" />
            <x-form.input name="agent" label="Agent" :value="request('agent')" placeholder="Agent name or username" />
            @if($activeTab === 'calls')
                <x-form.input name="phone" label="Phone" :value="request('phone')" placeholder="Phone number" />
                <x-form.select name="status" label="Status" :options="$statusOptions" :selected="request('status')" empty="All statuses" />
            @endif
            <div class="form-actions-bottom">
                <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
                <a href="{{ route('admin.records.index', ['tab' => $activeTab]) }}" class="btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

@if($activeTab === 'calls')
    <x-table.index caption="Call session records" role="tabpanel" aria-labelledby="records-tab-calls">
        <x-table.head :columns="[
            ['label' => 'Date/Time'],
            ['label' => 'Agent'],
            ['label' => 'Lead ID'],
            ['label' => 'Phone'],
            ['label' => 'Status'],
            ['label' => 'Disposition'],
            ['label' => 'Duration'],
            ['label' => 'End Reason'],
        ]" />
        @if($callSessions->isEmpty())
            <x-table.empty :colspan="8" message="No call sessions found." description="Actual dialed call activity for this campaign will appear here." />
        @else
        <tbody>
            @foreach($callSessions as $row)
                <tr>
                    <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">
                        {{ ($row->dialed_at ?? $row->created_at)?->format('Y-m-d H:i') }}
                    </td>
                    <td>{{ $row->user?->full_name ?? $row->user?->name ?? $row->user?->username ?? '-' }}</td>
                    <td class="font-mono text-sm">{{ $row->lead_id ?? '-' }}</td>
                    <td class="font-mono text-sm">{{ $row->phone_number ?? '-' }}</td>
                    <td><x-badge :type="$statusBadge($row->status)">{{ str($row->status ?? 'unknown')->headline() }}</x-badge></td>
                    <td>{{ $row->disposition_label ?? $row->disposition_code ?? '-' }}</td>
                    <td class="font-mono text-sm">{{ $formatDuration($row->call_duration_seconds) }}</td>
                    <td>{{ $row->end_reason ? str($row->end_reason)->headline() : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        @endif
        <x-slot:footer>
            <x-table.pagination :paginator="$callSessions" />
        </x-slot:footer>
    </x-table.index>
@else
    <x-table.index caption="Submitted CRM records" role="tabpanel" aria-labelledby="records-tab-submissions">
        <x-table.head :columns="[
            ['label' => 'Date'],
            ['label' => 'Form'],
            ['label' => 'Agent'],
            ['label' => 'Phone'],
            ['label' => 'Status'],
        ]" />
        @if($submissions->isEmpty())
            <x-table.empty :colspan="5" message="No submitted records found." description="CRM form submissions for this campaign will appear here." />
        @else
        <tbody>
            @foreach($submissions as $row)
                <tr>
                    <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $row->form_type }}</td>
                    <td>{{ $row->agent }}</td>
                    <td class="font-mono text-sm">{{ $row->phone_number ?? '-' }}</td>
                    <td><x-badge type="active">{{ $row->status ?? 'RECORDED' }}</x-badge></td>
                </tr>
            @endforeach
        </tbody>
        @endif
        <x-slot:footer>
            <x-table.pagination :paginator="$submissions" />
        </x-slot:footer>
    </x-table.index>
@endif
@endsection
