@extends('layouts.app')

@section('title', 'User Access - Admin')
@section('header-icon')
    <span class="mr-3 text-indigo-600">👥</span>
@endsection
@section('header-title')
    User Access
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
            <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-wrap gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}" required class="px-3 py-2 border border-gray-200 rounded @error('username') border-red-500 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Full name</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required class="px-3 py-2 border border-gray-200 rounded @error('full_name') border-red-500 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Password</label>
                    <input type="password" name="password" class="px-3 py-2 border border-gray-200 rounded @error('password') border-red-500 @enderror" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Confirm</label>
                    <input type="password" name="password_confirmation" class="px-3 py-2 border border-gray-200 rounded" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Role</label>
                    <select name="role" class="px-3 py-2 border border-gray-200 rounded">
                        @foreach(['Agent','Team Leader','Admin','Super Admin'] as $r)
                            <option value="{{ $r }}" {{ old('role') === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Username</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Full name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Role</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $usr)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $usr->username }}</td>
                            <td class="py-3 px-4">{{ $usr->full_name ?? $usr->name }}</td>
                            <td class="py-3 px-4">{{ $usr->role }}</td>
                            <td class="py-3 px-4 text-right">
                                <button type="button" onclick="document.getElementById('edit-row-{{ $usr->id }}').classList.toggle('hidden'); this.textContent = document.getElementById('edit-row-{{ $usr->id }}').classList.contains('hidden') ? 'Edit' : 'Cancel';" class="text-indigo-600 hover:underline text-xs mr-2">Edit</button>
                                @if($usr->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy') }}" class="inline" onsubmit="return confirm('Delete this user?');">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $usr->id }}">
                                        <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-xs">(you)</span>
                                @endif
                            </td>
                        </tr>
                        <tr id="edit-row-{{ $usr->id }}" class="hidden bg-indigo-50 border-b border-gray-100">
                            <td colspan="4" class="py-4 px-4">
                                <form method="POST" action="{{ route('admin.users.update', $usr) }}" class="flex flex-wrap gap-4 items-end">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Username</label>
                                        <input type="text" name="username" value="{{ old('username', $usr->username) }}" required class="px-3 py-2 border rounded w-40 @error('username') border-red-500 @enderror">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Full name</label>
                                        <input type="text" name="full_name" value="{{ old('full_name', $usr->full_name ?? $usr->name) }}" required class="px-3 py-2 border rounded w-48 @error('full_name') border-red-500 @enderror">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Role</label>
                                        <select name="role" class="px-3 py-2 border rounded">
                                            @foreach(['Agent','Team Leader','Admin','Super Admin'] as $r)
                                                <option value="{{ $r }}" {{ old('role', $usr->role) === $r ? 'selected' : '' }}>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">New password (leave blank to keep)</label>
                                        <input type="password" name="password" class="px-3 py-2 border rounded w-40">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Confirm</label>
                                        <input type="password" name="password_confirmation" class="px-3 py-2 border rounded w-40">
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
