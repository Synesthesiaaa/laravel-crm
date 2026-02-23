@extends('layouts.app')

@section('title', 'Attendance Logs - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">⏱</span>
@endsection

@section('header-title')
    Attendance Logs
@endsection

@section('content')
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Date</label>
                    <input type="date" name="date" value="{{ request('date') }}" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Filter</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">User</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Event</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Time</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">{{ $log->user->full_name ?? $log->user->username ?? $log->user_id }}</td>
                            <td class="py-3 px-4">{{ $log->event_type }}</td>
                            <td class="py-3 px-4">{{ $log->event_time?->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 px-4">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 px-4 text-center text-gray-500">No attendance logs.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
