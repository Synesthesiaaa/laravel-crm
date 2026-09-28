@extends('layouts.app')

@section('title', 'Configuration - Admin')
@section('header-icon')<x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'System Configuration')

@section('content')
<x-page-header title="System Configuration" description="Manage system-wide settings, integrations, reporting rules, and data lifecycle controls."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Configuration' => null]" />

<div class="md-card">
    <x-admin.tabs
        label="System configuration sections"
        :active="$tab"
        :tabs="[
            ['key' => 'general', 'label' => 'General', 'icon' => 'adjustments-horizontal', 'href' => route('admin.configuration', ['tab' => 'general'])],
            ['key' => 'branding', 'label' => 'Branding', 'icon' => 'building-office', 'href' => route('admin.configuration', ['tab' => 'branding'])],
            ['key' => 'disposition', 'label' => 'Disposition', 'icon' => 'tag', 'href' => route('admin.configuration', ['tab' => 'disposition'])],
            ['key' => 'telephony', 'label' => 'Telephony Features', 'icon' => 'phone', 'href' => route('admin.configuration', ['tab' => 'telephony'])],
            ['key' => 'diagnostics', 'label' => 'Diagnostics', 'icon' => 'signal', 'href' => route('admin.configuration', ['tab' => 'diagnostics'])],
            ['key' => 'retention', 'label' => 'Data Retention', 'icon' => 'clock', 'href' => route('admin.configuration', ['tab' => 'retention'])],
        ]" />
    <div class="p-6">
        @if(session('status'))
            <x-alert type="success" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        @include('admin.configuration.'.$tab)
    </div>
</div>
@endsection
