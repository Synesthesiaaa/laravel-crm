@extends('layouts.app')

@section('title', 'Campaigns - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">📢</span>
@endsection

@section('header-title')
    Campaigns
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
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <form method="POST" action="{{ route('admin.campaigns.store') }}" class="flex flex-wrap gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Code</label>
                    <input type="text" name="code" value="{{ old('code') }}" required class="px-3 py-2 border border-gray-200 rounded @error('code') border-red-500 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="px-3 py-2 border border-gray-200 rounded @error('name') border-red-500 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" class="px-3 py-2 border border-gray-200 rounded">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Code</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Active</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campaigns as $c)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $c->code }}</td>
                            <td class="py-3 px-4">{{ $c->name }}</td>
                            <td class="py-3 px-4">{{ $c->is_active ? 'Yes' : 'No' }}</td>
                            <td class="py-3 px-4 text-right">
                                <button type="button" onclick="document.getElementById('edit-campaign-{{ $c->id }}').classList.toggle('hidden'); this.textContent = document.getElementById('edit-campaign-{{ $c->id }}').classList.contains('hidden') ? 'Edit' : 'Cancel';" class="text-indigo-600 hover:underline text-xs mr-2">Edit</button>
                                <a href="{{ route('admin.forms.index', ['campaign' => $c->code]) }}" class="text-indigo-600 hover:underline text-xs mr-2">Forms</a>
                                <form method="POST" action="{{ route('admin.campaigns.destroy') }}" class="inline" onsubmit="return confirm('Deactivate this campaign?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $c->id }}">
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Deactivate</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-campaign-{{ $c->id }}" class="hidden bg-indigo-50 border-b border-gray-100">
                            <td colspan="4" class="py-4 px-4">
                                <form method="POST" action="{{ route('admin.campaigns.update', $c) }}" class="flex flex-wrap gap-4 items-end">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Code</label>
                                        <input type="text" name="code" value="{{ old('code', $c->code) }}" required class="px-3 py-2 border rounded w-40 @error('code') border-red-500 @enderror">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Name</label>
                                        <input type="text" name="name" value="{{ old('name', $c->name) }}" required class="px-3 py-2 border rounded w-48 @error('name') border-red-500 @enderror">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Description</label>
                                        <input type="text" name="description" value="{{ old('description', $c->description) }}" class="px-3 py-2 border rounded w-64">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Display order</label>
                                        <input type="number" name="display_order" value="{{ old('display_order', $c->display_order) }}" class="px-3 py-2 border rounded w-24">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" id="active-{{ $c->id }}" {{ old('is_active', $c->is_active) ? 'checked' : '' }}>
                                        <label for="active-{{ $c->id }}" class="text-sm">Active</label>
                                    </div>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Update</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
