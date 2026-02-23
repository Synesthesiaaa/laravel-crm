@extends('layouts.app')

@section('title', 'Data Master - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">💾</span>
@endsection

@section('header-title')
    Data Master
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    <x-validation-errors />
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.data-master.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Form type</label>
                    <select name="type" class="px-3 py-2 border border-gray-200 rounded">
                        @foreach($forms as $code => $config)
                            <option value="{{ $code }}" {{ $type === $code ? 'selected' : '' }}>{{ $config['name'] ?? $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Load</button>
            </form>
        </div>
        @if($tableName)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            @foreach($columns as $col)
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">{{ $col }}</th>
                            @endforeach
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $row)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                @foreach($columns as $col)
                                    <td class="py-3 px-4">{{ is_object($row) ? ($row->$col ?? '') : ($row[$col] ?? '') }}</td>
                                @endforeach
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('admin.data-master.edit', ['id' => $row->id ?? $row['id'], 'type' => $type]) }}" class="text-indigo-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('admin.data-master.destroy') }}" class="inline ml-2" onsubmit="return confirm('Delete this record?');">
                                        @csrf
                                        <input type="hidden" name="_table" value="{{ $tableName }}">
                                        <input type="hidden" name="_id" value="{{ $row->id ?? $row['id'] }}">
                                        <input type="hidden" name="_type" value="{{ $type }}">
                                        <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) + 1 }}" class="py-8 px-4 text-center text-gray-500">No records.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">
                {{ $records->withQueryString()->links() }}
            </div>
        @else
            <div class="p-8 text-center text-gray-500">Select a form type above.</div>
        @endif
    </div>
@endsection
