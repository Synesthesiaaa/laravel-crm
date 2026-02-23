@extends('layouts.app')

@section('title', 'Configuration - Admin')

@section('header-icon')
    <span class="mr-3 text-indigo-600">⚙</span>
@endsection

@section('header-title')
    Configuration
@endsection

@section('content')
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex gap-4">
            <a href="?tab=general" class="px-4 py-2 rounded {{ ($tab ?? '') === 'general' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">General</a>
            <a href="?tab=disposition" class="px-4 py-2 rounded {{ ($tab ?? '') === 'disposition' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Disposition</a>
        </div>
        <div class="p-6">
            @if(($tab ?? '') === 'disposition')
                <p class="text-gray-600">Disposition codes are managed per campaign. Default codes are seeded.</p>
            @else
                <p class="text-gray-600">Campaigns and forms are loaded from the database.</p>
                <p class="text-sm text-gray-500 mt-2">Campaigns: {{ count($campaigns ?? []) }}</p>
            @endif
        </div>
    </div>
@endsection
