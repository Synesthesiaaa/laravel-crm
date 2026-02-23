@extends('layouts.app')

@section('title', 'Disposition Codes - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">🏷</span>
@endsection

@section('header-title')
    Disposition Codes
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    <x-validation-errors />
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.disposition-codes.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Campaign</label>
                    <select name="campaign" class="px-3 py-2 border border-gray-200 rounded">
                        <option value="">Global</option>
                        @foreach($campaigns as $code => $config)
                            <option value="{{ $code }}" {{ $filterCampaign === $code ? 'selected' : '' }}>{{ $config['name'] ?? $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">View</button>
            </form>
        </div>
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h3 class="font-bold text-gray-900 mb-2">Add code</h3>
            <form method="POST" action="{{ route('admin.disposition-codes.store') }}" class="flex flex-wrap gap-4 items-end">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $filterCampaign }}">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Code</label>
                    <input type="text" name="code" required class="px-3 py-2 border border-gray-200 rounded" placeholder="e.g. SALE">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Label</label>
                    <input type="text" name="label" required class="px-3 py-2 border border-gray-200 rounded" placeholder="Sale">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Order</label>
                    <input type="number" name="sort_order" value="0" class="px-3 py-2 border border-gray-200 rounded w-20">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Code</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Label</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Order</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Active</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($codes as $code)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">{{ $code->code }}</td>
                            <td class="py-3 px-4">{{ $code->label }}</td>
                            <td class="py-3 px-4">{{ $code->sort_order }}</td>
                            <td class="py-3 px-4">{{ $code->is_active ? 'Yes' : 'No' }}</td>
                            <td class="py-3 px-4 text-right">
                                <form method="POST" action="{{ route('admin.disposition-codes.update', $code->id) }}" class="inline-block mr-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="code" value="{{ $code->code }}" class="px-2 py-1 border rounded text-xs w-24">
                                    <input type="text" name="label" value="{{ $code->label }}" class="px-2 py-1 border rounded text-xs w-32">
                                    <input type="number" name="sort_order" value="{{ $code->sort_order }}" class="px-2 py-1 border rounded text-xs w-16">
                                    <input type="hidden" name="is_active" value="1">
                                    <button type="submit" class="px-2 py-1 bg-gray-200 rounded text-xs">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.disposition-codes.destroy') }}" class="inline-block" onsubmit="return confirm('Delete this code?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $code->id }}">
                                    <button type="submit" class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 px-4 text-center text-gray-500">No disposition codes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
