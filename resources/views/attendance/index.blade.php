@extends('layouts.app')

@section('title', 'My Attendance')
@section('header-icon')<x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'My Attendance')

@section('content')
<x-page-header title="My Attendance" :breadcrumbs="['Attendance' => null]" />

<div class="md-hero mb-6">
    <h2 class="text-lg font-bold text-[var(--color-on-surface)]">{{ $user->full_name ?? $user->name ?? $user->username }}</h2>
    <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">Your login and attendance events.</p>
    @if($lastEvent)
        <p class="text-[var(--color-on-surface-muted)] mt-3 text-sm">
            Last event:
            <x-badge :type="$lastEvent->event_type === 'login' ? 'active' : 'inactive'">{{ strtoupper($lastEvent->event_type) }}</x-badge>
            <span class="ml-1">{{ $lastEvent->event_time?->format('M j, Y g:i A') }}</span>
        </p>
    @endif
</div>

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('attendance.index') }}" class="flex items-end gap-4">
            <x-form.input name="date" type="date" label="Date" :value="$date" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-primary">
                    <x-icon name="magnifying-glass" class="w-4 h-4" />
                    View
                </button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Attendance events">
    <x-table.head :columns="[['label' => 'Event'], ['label' => 'Pause code'], ['label' => 'Time'], ['label' => 'IP']]" />
    @if(!isset($logs) || $logs->isEmpty())
        <x-table.empty :colspan="4" message="No attendance events for this date." />
    @else
    <tbody>
        @foreach($logs as $log)
            <tr>
                <td>
                    <x-badge :type="$log->event_type === 'login' ? 'active' : ($log->event_type === 'logout' ? 'inactive' : ($log->event_type === 'pause' ? 'pending' : 'info'))">
                        {{ strtoupper($log->event_type) }}
                    </x-badge>
                </td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)]">{{ $log->pause_code ?? '—' }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)]">{{ $log->event_time?->format('Y-m-d H:i:s') }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-dim)]">{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>
@endsection
