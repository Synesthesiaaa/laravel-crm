@extends('layouts.app')

@section('title', 'Disposition Records - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">📞</span>
@endsection

@section('header-title')
    Disposition Records
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.disposition-records.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Agent</label>
                    <input type="text" name="agent" value="{{ request('agent') }}" class="px-3 py-2 border border-gray-200 rounded w-40">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Disposition</label>
                    <select name="disposition" class="px-3 py-2 border border-gray-200 rounded w-48">
                        <option value="">All</option>
                        @foreach($dispositionCodes as $dc)
                            <option value="{{ $dc->code }}" {{ request('disposition') === $dc->code ? 'selected' : '' }}>{{ $dc->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">From</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">To</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Filter</button>
                <a href="{{ route('admin.disposition-records.index') }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Clear</a>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Called at</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Phone</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Lead ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Agent</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Disposition</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $r->called_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 px-4">{{ $r->phone_number ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $r->lead_id ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $r->agent }}</td>
                            <td class="py-3 px-4">{{ $r->disposition_label ?? $r->disposition_code }}</td>
                            <td class="py-3 px-4 text-right">{{ $r->call_duration_seconds ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 px-4 text-center text-gray-500">No disposition records.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $records->withQueryString()->links() }}
        </div>
    </div>
@endsection
