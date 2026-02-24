@extends('layouts.app')

@section('title', 'Configuration - Admin')
@section('header-icon')<x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'System Configuration')

@section('content')
<x-page-header title="System Configuration"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Configuration' => null]" />

<div class="md-card">
    <div class="flex gap-2 p-4 border-b border-[var(--color-border)]">
        <a href="?tab=general"
           class="{{ ($tab ?? '') !== 'disposition' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            General
        </a>
        <a href="?tab=disposition"
           class="{{ ($tab ?? '') === 'disposition' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Disposition
        </a>
    </div>
    <div class="p-6">
        @if(($tab ?? '') === 'disposition')
            <x-alert type="info">
                Disposition codes are managed per campaign from the
                <a href="{{ route('admin.disposition-codes.index') }}" class="link-primary">Disposition Codes</a> page.
            </x-alert>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-card label="Active Campaigns"
                    :value="count($campaigns ?? [])"
                    icon="building-office"
                    :href="route('admin.campaigns.index')" />
            </div>
            <div class="mt-6">
                <x-alert type="info" title="Configuration Note">
                    Campaigns and forms are loaded from the database. Use the
                    <a href="{{ route('admin.campaigns.index') }}" class="link-primary">Campaigns</a> and
                    <a href="{{ route('admin.forms.index') }}" class="link-primary">Forms</a> pages to manage them.
                </x-alert>
            </div>
        @endif
    </div>
</div>
@endsection
