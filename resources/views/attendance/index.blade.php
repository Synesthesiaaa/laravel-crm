@extends('layouts.app')

@section('title', 'My Attendance')
@section('header-icon')<x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'My Attendance')

@section('content')
<x-page-header
    title="My Attendance"
    description="Review your attendance activity and manage your current away status."
    :breadcrumbs="['Attendance' => null]"
/>

<div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
    <div class="md-hero">
        <div class="flex h-full flex-col gap-5">
            <div class="min-w-0 flex-1">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">Attendance overview</p>
                <h2 class="text-lg font-bold text-[var(--color-on-surface)]">{{ $user->full_name ?? $user->name ?? $user->username }}</h2>
                <p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">Your login, logout, and away-status activity is recorded here.</p>
            </div>
            @if($lastEvent)
                <div class="flex flex-wrap items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                    <span class="font-medium text-[var(--color-on-surface-dim)]">Last recorded event</span>
                    <x-attendance.event-badge :log="$lastEvent" />
                    <span>{{ $lastEvent->event_time?->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}</span>
                </div>
            @endif
            <x-app-live-clock class="mt-auto w-full" />
        </div>
    </div>
    <x-attendance.status-panel class="h-full" />
</div>

<div class="md-card mb-4">
    <div class="p-4 sm:p-5">
        <form method="GET" action="{{ route('attendance.index') }}" data-soft-nav class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <x-form.input name="date" type="date" label="Attendance date" :value="$date" class="sm:min-w-56" />
            <div class="form-actions-bottom flex w-full gap-2 sm:w-auto">
                <button type="submit" class="btn-primary flex-1 sm:flex-none">
                    <x-icon name="magnifying-glass" class="w-4 h-4" />
                    View activity
                </button>
                @if($date !== now()->format('Y-m-d'))
                    <a href="{{ route('attendance.index') }}" class="btn-secondary flex-1 sm:flex-none">Today</a>
                @endif
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Attendance events">
    <x-table.head :columns="[['label' => 'Event'], ['label' => 'Time'], ['label' => 'IP']]" />
    @if(!isset($logs) || $logs->isEmpty())
        <x-table.empty :colspan="3" message="No attendance events for this date." />
    @else
    <tbody>
        @foreach($logs as $log)
            <tr>
                <td>
                    <x-attendance.event-badge :log="$log" />
                </td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)]">{{ $log->event_time?->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-dim)]">{{ $log->ip_address ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>

@endsection
