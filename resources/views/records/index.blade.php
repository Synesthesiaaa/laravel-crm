@extends('layouts.app')

@section('title', 'Call History')
@section('header-icon')<x-icon name="clipboard-document-list" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Call History')

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
@endphp

@section('content')
<x-page-header title="Call History" description="Your call activity and outcomes."
    :breadcrumbs="['Call History' => null]" />

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('records.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="start_date" type="date" label="Start Date" :value="request('start_date')" />
            <x-form.input name="end_date" type="date" label="End Date" :value="request('end_date')" />
            <x-form.input name="phone" label="Phone" :value="request('phone')" placeholder="Phone number" />
            <x-form.select name="status" label="Status" :options="$statusOptions" :selected="request('status')" empty="All statuses" />
            <div class="form-actions-bottom">
                <button type="submit" class="btn-primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    Filter
                </button>
            </div>
            @if(request()->hasAny(['start_date','end_date','phone','status']))
                <div class="form-actions-bottom">
                    <a href="{{ route('records.index') }}" class="btn-ghost">Clear</a>
                </div>
            @endif
        </form>
    </div>
</div>

<x-table.index caption="Call session history">
    <x-table.head :columns="[
        ['label' => 'Date/Time'],
        ['label' => 'Lead ID'],
        ['label' => 'Phone'],
        ['label' => 'Status'],
        ['label' => 'Disposition'],
        ['label' => 'Duration'],
        ['label' => 'End Reason'],
    ]" />
    @if($history->isEmpty())
        <x-table.empty :colspan="7" message="No call sessions found." description="Your actual call activity will appear here after dialing." />
    @else
    <tbody>
        @foreach($history as $row)
            <tr>
                <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">
                    {{ ($row->dialed_at ?? $row->created_at)?->format('Y-m-d H:i') }}
                </td>
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
</x-table.index>
<x-table.pagination :paginator="$history" />
@endsection
