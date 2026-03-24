@extends('layouts.app')

@section('title', 'Attendance Logs - Admin')
@section('header-icon')<x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Attendance Logs')

@section('content')
<x-page-header title="Staff Attendance" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Attendance' => null]" />

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="date" type="date" label="Date" :value="request('date')" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Attendance log entries">
    <x-table.head :columns="[['label' => 'User'], ['label' => 'Event'], ['label' => 'Pause code'], ['label' => 'Time'], ['label' => 'IP']]" />
    @if(isset($logs) && $logs->isEmpty())
        <x-table.empty :colspan="5" message="No attendance logs." />
    @else
    <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="font-medium">{{ $log->user->full_name ?? $log->user->username ?? $log->user_id }}</td>
                <td>
                    <x-badge :type="$log->event_type === 'login' ? 'active' : ($log->event_type === 'logout' ? 'inactive' : ($log->event_type === 'pause' ? 'pending' : 'info'))">
                        {{ strtoupper($log->event_type) }}
                    </x-badge>
                </td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)]">{{ $log->pause_code ?? '—' }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)]">{{ $log->event_time?->format('Y-m-d H:i:s') }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-dim)]">{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="No attendance logs." />
        @endforelse
    </tbody>
    @endif
</x-table.index>
@endsection
