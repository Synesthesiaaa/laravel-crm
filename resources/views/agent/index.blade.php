@extends('layouts.app')

@section('title', 'Agent - ' . ($campaignName ?? 'CRM'))

@section('header-icon')
    <span class="mr-3 text-indigo-600">🎧</span>
@endsection

@section('header-title')
    Agent
@endsection

@section('content')
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow border border-gray-100 p-6">
        <p class="text-gray-600 mb-6">Softphone and VICIdial integration. Use the API proxy for dial, hangup, pause, park, transfer.</p>
        <div class="space-y-4">
            <p class="text-sm text-gray-500">Campaign: <strong>{{ $campaign }}</strong></p>
            <p class="text-sm text-gray-500">VICIdial credentials are taken from your user profile. Ensure a VICIdial server is configured for this campaign in Admin.</p>
        </div>
    </div>
@endsection
