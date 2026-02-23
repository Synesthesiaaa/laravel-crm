@extends('layouts.app')

@section('title', 'Attendance - ' . ($campaignName ?? 'CRM'))

@section('header-icon')
    <span class="text-[var(--color-primary)]">⏱</span>
@endsection

@section('header-title')
    My Attendance
@endsection

@section('content')
    <div class="space-y-8">
        <div class="md-hero py-6 px-8">
            <h2 class="text-xl font-bold mb-2 text-[var(--color-on-surface)]">Hello, {{ $user->full_name ?? $user->name ?? $user->username }}</h2>
            <p class="text-[var(--color-on-surface-muted)] text-sm">View your login and attendance events below.</p>
            @if($lastEvent)
                <p class="text-[var(--color-on-surface-muted)] mt-2 text-sm">Last event: <strong class="text-[var(--color-primary)]">{{ $lastEvent->event_type }}</strong> at {{ $lastEvent->event_time?->format('M j, Y g:i A') }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <form method="GET" action="{{ route('attendance.index') }}" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Date</label>
                        <input type="date" name="date" value="{{ $date }}" class="px-3 py-2 border border-gray-200 rounded">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">View</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">Event</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">Time</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4">{{ $log->event_type }}</td>
                                <td class="py-3 px-4">{{ $log->event_time?->format('Y-m-d H:i:s') }}</td>
                                <td class="py-3 px-4">{{ $log->ip_address ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 px-4 text-center text-gray-500">No attendance events for this date.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
