@extends('layouts.app')

@section('title', 'ViciDial Servers - Admin')
@section('header-icon')
    <span class="mr-3 text-indigo-600">🖥</span>
@endsection
@section('header-title')
    ViciDial Servers
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
            <form method="POST" action="{{ route('admin.vicidial-servers.store') }}">
                @csrf
                <div class="flex flex-wrap gap-4 items-end">
                    <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Campaign</label><select name="campaign_code" required class="px-3 py-2 border rounded">@foreach($campaigns as $code => $c)<option value="{{ $code }}">{{ $c['name'] ?? $code }}</option>@endforeach</select></div>
                    <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Server name</label><input type="text" name="server_name" required class="px-3 py-2 border rounded"></div>
                    <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">API URL</label><input type="url" name="api_url" required class="px-3 py-2 border rounded"></div>
                    <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">DB host</label><input type="text" name="db_host" required class="px-3 py-2 border rounded"></div>
                    <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">DB username</label><input type="text" name="db_username" required class="px-3 py-2 border rounded"></div>
                    <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">DB password</label><input type="password" name="db_password" class="px-3 py-2 border rounded"></div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Add</button>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Campaign</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Server</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">API URL</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($servers as $s)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">{{ $s->campaign_code }}</td>
                            <td class="py-3 px-4">{{ $s->server_name }}</td>
                            <td class="py-3 px-4">{{ $s->api_url }}</td>
                            <td class="py-3 px-4 text-right">
                                <form method="POST" action="{{ route('admin.vicidial-servers.destroy') }}" class="inline" onsubmit="return confirm('Delete?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $s->id }}">
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 px-4 text-center text-gray-500">No servers.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
