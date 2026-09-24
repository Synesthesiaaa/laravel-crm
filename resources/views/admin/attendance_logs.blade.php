@extends('layouts.app')

@section('title', 'Attendance Logs - Admin')
@section('header-icon')<x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Attendance Logs')

@section('content')
<x-page-header
    title="Staff Attendance"
    description="Review staff login, logout, and away-status activity from one audit view."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Attendance' => null]"
/>

@php
    $hasFilters = $filters['user_id'] !== null || $filters['date'] !== null || $filters['event'] !== null;
@endphp

<x-attendance.realtime-table />

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-stat-card
        label="Visible events"
        :value="number_format($summary['total'])"
        icon="clipboard-document-list"
        color="primary"
        :secondary="'Latest '.$resultLimit.' matching events max'"
    />
    <x-stat-card
        label="Logins"
        :value="number_format($summary['login'])"
        icon="arrow-right-on-rectangle"
        color="success"
        secondary="In the visible result set"
    />
    <x-stat-card
        label="Logouts"
        :value="number_format($summary['logout'])"
        icon="x-circle"
        color="warning"
        secondary="In the visible result set"
    />
    <x-stat-card
        label="Away-status events"
        :value="number_format($summary['away'])"
        icon="clock"
        color="info"
        secondary="Starts and ends combined"
    />
</div>

<section class="md-card md-card--static mb-4" aria-labelledby="attendance-filter-heading">
    <div class="p-4 sm:p-5">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h2 id="attendance-filter-heading" class="text-base font-semibold text-[var(--color-on-surface)]">Filter attendance activity</h2>
                <p id="attendance-filter-help" class="mt-1 text-sm text-[var(--color-on-surface-muted)]">
                    Narrow the audit view by date or event type. Results are ordered newest first.
                </p>
            </div>
            <x-app-live-clock label="Current time" class="w-full lg:w-auto lg:min-w-[19rem]" />
        </div>

        <form
            method="GET"
            action="{{ route('admin.attendance.index') }}"
            data-soft-nav
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,16rem)_minmax(14rem,18rem)_auto]"
            aria-describedby="attendance-filter-help"
        >
            @if($filters['user_id'] !== null)
                <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
            @endif
            <x-form.input
                name="date"
                type="date"
                label="Attendance date"
                :value="$filters['date']"
            />
            <x-form.select
                name="event"
                label="Event type"
                :options="$eventOptions"
                :selected="$filters['event']"
                empty="All events"
            />
            <div class="form-actions-bottom flex gap-2 sm:col-span-2 lg:col-span-1">
                <button type="submit" class="btn-primary flex-1 sm:flex-none">
                    <x-icon name="funnel" class="w-4 h-4" />
                    Apply filters
                </button>
                @if($hasFilters)
                    <a href="{{ route('admin.attendance.index') }}" class="btn-secondary flex-1 sm:flex-none">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>
</section>

<div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-base font-semibold text-[var(--color-on-surface)]">Attendance activity</h2>
        <p class="text-sm text-[var(--color-on-surface-muted)]">
            Showing {{ number_format($logs->count()) }} {{ \Illuminate\Support\Str::plural('event', $logs->count()) }}
            @if($hasFilters)
                matching the current filters
            @else
                from the latest recorded activity
            @endif
            .
        </p>
    </div>
    @if($logs->count() === $resultLimit)
        <p class="text-xs text-[var(--color-on-surface-dim)]">Limited to the latest {{ number_format($resultLimit) }} matching events.</p>
    @endif
</div>

<x-table.index caption="Attendance log entries">
    <x-table.head :columns="[['label' => 'Staff member'], ['label' => 'Event'], ['label' => 'Recorded at'], ['label' => 'IP address']]" />
    @if($logs->isEmpty())
        <x-table.empty
            :colspan="4"
            message="No attendance activity found."
            description="Try a different date or event type, or clear the current filters."
        >
            @if($hasFilters)
                <a href="{{ route('admin.attendance.index') }}" class="btn-secondary text-xs">Clear filters</a>
            @endif
        </x-table.empty>
    @else
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>
                    <div class="min-w-0">
                        <div class="font-medium text-[var(--color-on-surface)]">
                            {{ $log->user->full_name ?? $log->user->username ?? 'User #'.$log->user_id }}
                        </div>
                        @if($log->user?->username && $log->user->username !== $log->user->full_name)
                            <div class="mt-0.5 text-xs text-[var(--color-on-surface-dim)]">{{ '@'.$log->user->username }}</div>
                        @endif
                    </div>
                </td>
                <td>
                    <x-attendance.event-badge :log="$log" />
                </td>
                <td><x-attendance.event-time :log="$log" /></td>
                <td class="font-mono text-sm tabular-nums text-[var(--color-on-surface-dim)]">{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    @endif
</x-table.index>
@endsection
