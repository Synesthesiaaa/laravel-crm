@extends('layouts.app')

@section('title', 'Agent Screen - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">🎧</span>
@endsection

@section('header-title')
    Agent Screen Configuration
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.agent-screen.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Campaign</label>
                    <select name="campaign" class="px-3 py-2 border border-gray-200 rounded">
                        @foreach($campaigns as $code => $config)
                            <option value="{{ $code }}" {{ $selectedCampaign === $code ? 'selected' : '' }}>{{ $config['name'] ?? $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Load</button>
            </form>
        </div>
        <div class="p-6 border-b border-gray-100 bg-amber-50/80 text-sm text-gray-700">
            <p class="font-semibold text-gray-900 mb-1">Auto-fill from leads</p>
            <p class="mb-1">Use a <strong>Field key</strong> that matches a standard lead column or a custom list column so the agent screen pre-fills from hopper/lead data.</p>
            <p class="text-xs text-gray-600 font-mono break-all">{{ implode(', ', $leadStandardFieldKeys ?? []) }}</p>
        </div>
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h3 class="font-bold text-gray-900 mb-2">Add field</h3>
            <form method="POST" action="{{ route('admin.agent-screen.store') }}" class="flex flex-wrap gap-4 items-end">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $selectedCampaign }}">
                <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Field key (e.g. phone_number, lead_id)</label><input type="text" name="field_key" required pattern="[a-zA-Z0-9_]+" class="px-3 py-2 border rounded"></div>
                <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Label</label><input type="text" name="field_label" required class="px-3 py-2 border rounded"></div>
                <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Width</label><select name="field_width" class="px-3 py-2 border rounded"><option value="full">Full</option><option value="half">Half</option><option value="third">Third</option></select></div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Key</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Label</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Width</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fields as $f)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">{{ $f->field_key }}</td>
                            <td class="py-3 px-4">{{ $f->field_label }}</td>
                            <td class="py-3 px-4">{{ $f->field_width ?? 'full' }}</td>
                            <td class="py-3 px-4 text-right">
                                <form method="POST" action="{{ route('admin.agent-screen.destroy') }}" class="inline" onsubmit="return confirm('Remove this field?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $f->id }}">
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 px-4 text-center text-gray-500">No agent screen fields.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
