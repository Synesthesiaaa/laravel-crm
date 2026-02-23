@extends('layouts.app')

@section('title', 'Field Logic - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">📋</span>
@endsection

@section('header-title')
    Field Logic
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    <x-validation-errors />
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.field-logic.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Form</label>
                    <select name="form" class="px-3 py-2 border border-gray-200 rounded">
                        @foreach($forms as $code => $config)
                            <option value="{{ $code }}" {{ $formType === $code ? 'selected' : '' }}>{{ $config['name'] ?? $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Load</button>
            </form>
        </div>
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h3 class="font-bold text-gray-900 mb-2">Add field</h3>
            <form method="POST" action="{{ route('admin.field-logic.store') }}" class="flex flex-wrap gap-4 items-end">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $campaign }}">
                <input type="hidden" name="form_type" value="{{ $formType }}">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Field name</label>
                    <input type="text" name="field_name" required class="px-3 py-2 border border-gray-200 rounded" placeholder="column_name">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Label</label>
                    <input type="text" name="field_label" required class="px-3 py-2 border border-gray-200 rounded" placeholder="Display Label">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Type</label>
                    <select name="field_type" class="px-3 py-2 border border-gray-200 rounded">
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="select">Select</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_required" value="1" id="req">
                    <label for="req" class="text-sm">Required</label>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Label</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Required</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fields as $f)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">{{ $f->field_name }}</td>
                            <td class="py-3 px-4">{{ $f->field_label }}</td>
                            <td class="py-3 px-4">{{ $f->field_type }}</td>
                            <td class="py-3 px-4">{{ $f->is_required ? 'Yes' : 'No' }}</td>
                            <td class="py-3 px-4 text-right">
                                <form method="POST" action="{{ route('admin.field-logic.update', $f->id) }}" class="inline-block mr-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="field_label" value="{{ $f->field_label }}" class="px-2 py-1 border rounded text-xs w-32">
                                    <button type="submit" class="px-2 py-1 bg-gray-200 rounded text-xs">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.field-logic.destroy') }}" class="inline-block" onsubmit="return confirm('Delete this field?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $f->id }}">
                                    <button type="submit" class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 px-4 text-center text-gray-500">No fields. Add one above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
