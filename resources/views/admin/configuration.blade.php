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
           class="{{ !in_array(($tab ?? ''), ['disposition', 'telephony', 'diagnostics'], true) ? 'btn-primary' : 'btn-secondary' }} text-sm">
            General
        </a>
        <a href="?tab=disposition"
           class="{{ ($tab ?? '') === 'disposition' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Disposition
        </a>
        <a href="?tab=telephony"
           class="{{ ($tab ?? '') === 'telephony' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Telephony Features
        </a>
        <a href="?tab=diagnostics"
           class="{{ ($tab ?? '') === 'diagnostics' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Diagnostics
        </a>
    </div>
    <div class="p-6">
        @if(session('status'))
            <x-alert type="success" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        @if(($tab ?? '') === 'disposition')
            <x-alert type="info">
                Disposition codes are managed per campaign from the
                <a href="{{ route('admin.disposition-codes.index') }}" class="link-primary">Disposition Codes</a> page.
            </x-alert>
        @elseif(($tab ?? '') === 'telephony')
            <form method="POST" action="{{ route('admin.configuration.telephony-features.update') }}" class="space-y-5">
                @csrf
                @php
                    $featureLabels = [
                        'session_controls' => 'ViciDial Session Controls (login, pause, pause code, logout)',
                        'ingroup_management' => 'In-group Management',
                        'transfer_controls' => 'Transfer and Conference Controls',
                        'recording_controls' => 'Recording Controls',
                        'dtmf_controls' => 'DTMF Keypad',
                        'callback_controls' => 'Callback Scheduling',
                        'lead_tools' => 'Lead Search and Lead Tools',
                        'predictive_dialing' => 'Predictive Dialing',
                    ];
                @endphp

                <x-alert type="info" title="Feature Gating">
                    Disabled features are hidden from agent screen and blocked at API level for non-Super Admin users.
                </x-alert>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($featureLabels as $featureKey => $featureLabel)
                        <label class="flex items-center justify-between rounded-lg border border-[var(--color-border)] p-3">
                            <span class="text-sm text-[var(--color-on-surface)]">{{ $featureLabel }}</span>
                            <input type="checkbox"
                                   name="features[{{ $featureKey }}]"
                                   value="1"
                                   class="h-4 w-4 rounded border-[var(--color-border)]"
                                   @checked(($telephonyFeatures[$featureKey] ?? true) === true)>
                        </label>
                    @endforeach
                </div>

                <div>
                    <button type="submit" class="btn-primary">Save Telephony Feature Access</button>
                </div>
            </form>
        @elseif(($tab ?? '') === 'diagnostics')
            <div x-data="telephonyDiagnostics()" x-init="init()">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Telephony Readiness Check</h3>
                    <button type="button" class="btn-primary text-sm" @click="run()" {!! 'x-bind:disabled="loading"' !!}>
                        <span {!! 'x-bind:class="loading ? \'animate-spin\' : \'\'"' !!}><x-icon name="arrow-path" class="w-4 h-4" /></span>
                        <span x-text="loading ? 'Checking...' : 'Run Diagnostics'">Run Diagnostics</span>
                    </button>
                </div>

                <x-alert type="info" class="mb-4">
                    Checks live connectivity to ViciDial APIs, AMI, and validates per-campaign and per-agent credential completeness.
                    No data is modified.
                </x-alert>

                <template x-if="checks.length === 0 && !loading">
                    <p class="text-sm text-[var(--color-on-surface-dim)]">Click "Run Diagnostics" to test your ViciDial connection.</p>
                </template>

                <template x-if="loading && checks.length === 0">
                    <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-dim)]">
                        <x-icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        Running checks...
                    </div>
                </template>

                <template x-if="checks.length > 0">
                    <div class="space-y-2">
                        <template x-for="check in checks" :key="check.label">
                            <div class="flex items-start gap-3 rounded-lg border p-3"
                                 :class="{
                                     'border-green-500/40 bg-green-500/5':  check.status === 'ok',
                                     'border-amber-500/40 bg-amber-500/5':  check.status === 'warn',
                                     'border-red-500/40 bg-red-500/5':      check.status === 'fail',
                                 }">
                                <div class="shrink-0 mt-0.5">
                                    <template x-if="check.status === 'ok'">
                                        <x-icon name="check-circle" class="w-5 h-5 text-green-500" />
                                    </template>
                                    <template x-if="check.status === 'warn'">
                                        <x-icon name="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                                    </template>
                                    <template x-if="check.status === 'fail'">
                                        <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[var(--color-on-surface)]" x-text="check.label"></p>
                                    <p class="text-xs text-[var(--color-on-surface-muted)] mt-0.5 break-all" x-text="check.message"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
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

@if(($tab ?? '') === 'diagnostics')
@push('scripts')
<script>
window.telephonyDiagnostics = function () {
    return {
        loading: false,
        checks: [],
        async init() {
            await this.run();
        },
        async run() {
            this.loading = true;
            this.checks = [];
            try {
                const res = await window.axios.post('/admin/configuration/telephony-diagnostics');
                this.checks = res.data?.checks ?? [];
                if (res.data?.ok) {
                    Alpine.store('toast').success('All telephony checks passed.');
                } else {
                    Alpine.store('toast').warning('Some checks need attention. See details below.');
                }
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Diagnostics request failed.');
            } finally {
                this.loading = false;
            }
        },
    };
};
</script>
@endpush
@endif
