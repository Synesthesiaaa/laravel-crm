@extends('layouts.app')

@section('title', 'Extraction - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">📥</span>
@endsection

@section('header-title')
    Extraction of Data
@endsection

@section('content')
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    <x-validation-errors />
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden max-w-xl">
        <div class="p-6">
            <p class="text-gray-600 text-sm mb-6">Export form data to CSV by date range.</p>
            <form method="POST" action="{{ route('admin.extraction.export') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Start date</label>
                        <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-200 rounded">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">End date</label>
                        <input type="date" name="end_date" class="w-full px-3 py-2 border border-gray-200 rounded">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Data type</label>
                        <select name="data_type" class="w-full px-3 py-2 border border-gray-200 rounded">
                            <option value="all">All forms</option>
                            @foreach($forms as $code => $config)
                                <option value="{{ $code }}">{{ $config['name'] ?? $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Export CSV</button>
                </div>
            </form>
        </div>
    </div>
@endsection
