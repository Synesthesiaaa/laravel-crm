@extends('layouts.app')

@section('title', 'Call History - ' . ($campaign ?? 'CRM'))

@section('header-icon')
    <span class="text-[var(--color-primary)]">📜</span>
@endsection

@section('header-title')
    Call History
@endsection

@section('content')
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('records.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Agent</label>
                    <input type="text" name="agent" value="{{ request('agent') }}" placeholder="Agent name" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Filter</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Date</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Form</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Agent</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Phone</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="py-3 px-4">{{ $row->form_type }}</td>
                            <td class="py-3 px-4">{{ $row->agent }}</td>
                            <td class="py-3 px-4">{{ $row->phone_number ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $row->status ?? 'RECORDED' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 px-4 text-center text-gray-500">No call history found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $history->withQueryString()->links() }}
        </div>
    </div>
@endsection
