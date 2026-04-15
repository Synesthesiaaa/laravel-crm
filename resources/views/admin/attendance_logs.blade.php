@extends('layouts.app')

@section('title', 'Attendance Logs - Admin')
@section('header-icon')<x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Attendance Logs')

@section('content')
<x-page-header title="Staff Attendance" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Attendance' => null]" />

<div class="mb-4">
    <x-app-live-clock />
</div>

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="date" type="date" label="Date" :value="request('date')" />
            <div class="form-field min-w-[12rem]">
                <label class="form-label">{{ __('Event') }}</label>
                <select name="event" class="form-select">
                    <option value="">{{ __('All events') }}</option>
                    <option value="login" @selected(request('event') === 'login')>{{ __('Login') }}</option>
                    <option value="logout" @selected(request('event') === 'logout')>{{ __('Logout') }}</option>
                    @foreach($statusTypes ?? [] as $st)
                        <option value="{{ $st->id }}" @selected((string) request('event') === (string) $st->id)>
                            {{ $st->label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Attendance log entries">
    <x-table.head :columns="[['label' => 'User'], ['label' => 'Event'], ['label' => 'Time'], ['label' => 'IP']]" />
    <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="font-medium">{{ $log->user->full_name ?? $log->user->username ?? $log->user_id }}</td>
                <td>
                    <x-badge :type="$log->event_type === 'login' ? 'active' : ($log->event_type === 'logout' ? 'inactive' : 'info')">
                        {{ $log->eventDisplayLabel() }}
                    </x-badge>
                </td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)]">{{ $log->event_time?->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-dim)]">{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" message="No attendance logs." />
        @endforelse
    </tbody>
</x-table.index>
@endsection
