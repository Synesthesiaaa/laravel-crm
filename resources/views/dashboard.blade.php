@extends('layouts.app')

@section('title', 'Dashboard - ' . ($campaignName ?? 'CRM'))

@section('header-icon')
    <span class="text-[var(--color-primary)]">📊</span>
@endsection

@section('header-title')
    Analytics Overview
@endsection

@section('content')
    <div class="space-y-8">
        <div class="md-hero">
            <h2 class="text-2xl font-bold mb-2 text-[var(--color-on-surface)]">Hello, {{ $user->full_name ?? $user->name ?? $user->username }}</h2>
            <p class="text-[var(--color-on-surface-muted)] max-w-md">You are logged into the <strong class="text-[var(--color-primary)]">{{ $campaignName }}</strong> portal. Use the sidebar to navigate between forms and records.</p>
        </div>

        @if(!empty($activityTrend['labels']))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-stagger">
            <div class="md-card p-6">
                <h4 class="text-[var(--color-on-surface)] font-bold mb-6 flex items-center gap-2">
                    <span class="text-[var(--color-primary)]">📈</span> Campaign Activity (14 days)
                </h4>
                <div class="space-y-2">
                    @foreach(array_combine($activityTrend['labels'] ?? [], $activityTrend['values'] ?? []) ?: [] as $label => $value)
                        <div class="flex justify-between text-sm">
                            <span class="text-[var(--color-on-surface-muted)]">{{ $label }}</span>
                            <span class="font-semibold text-[var(--color-on-surface)]">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="md-card p-6">
                <h4 class="text-[var(--color-on-surface)] font-bold mb-6 flex items-center gap-2">
                    <span class="text-[var(--color-primary)]">🏆</span> Top Agents
                </h4>
                <div class="space-y-2">
                    @foreach(array_combine($topAgents['labels'] ?? [], $topAgents['values'] ?? []) ?: [] as $agent => $total)
                        <div class="flex justify-between text-sm">
                            <span class="text-[var(--color-on-surface-muted)]">{{ $agent }}</span>
                            <span class="font-semibold text-[var(--color-on-surface)]">{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest">Active Campaign Forms</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-stagger">
            @foreach($forms ?? [] as $formCode => $formConfig)
                <a href="{{ route('forms.show', ['type' => $formCode, 'campaign' => $campaign]) }}" class="md-card p-6 flex items-center gap-6 group no-underline">
                    <div class="w-14 h-14 rounded-xl bg-[var(--color-surface-2)] flex items-center justify-center text-2xl border border-[var(--color-border)] group-hover:border-[var(--color-primary)] transition-colors duration-200">📄</div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-lg font-bold text-[var(--color-on-surface)]">{{ $formConfig['name'] ?? $formCode }}</h4>
                        <p class="text-[var(--color-on-surface-dim)] text-xs uppercase tracking-wider mt-1">Submit New Lead</p>
                    </div>
                    <span class="text-[var(--color-on-surface-dim)] group-hover:text-[var(--color-primary)] transition-colors duration-200">→</span>
                </a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-stagger">
            <div class="md-card p-6 border-l-4 border-l-[var(--color-primary)]">
                <h4 class="font-bold text-[var(--color-on-surface)] uppercase tracking-wider text-xs mb-6 flex items-center justify-between">
                    Quick Summary
                    <span class="text-[var(--color-primary)]">📋</span>
                </h4>
                <div class="space-y-6">
                    <div class="flex justify-between items-end">
                        <span class="text-[var(--color-on-surface-muted)] text-sm">Active Forms</span>
                        <span class="text-2xl font-bold text-[var(--color-on-surface)]">{{ count($forms ?? []) }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-[var(--color-on-surface-muted)] text-sm">Campaign</span>
                        <span class="text-sm font-bold bg-[var(--color-surface-2)] px-3 py-1 rounded text-[var(--color-primary)] uppercase">{{ $campaign }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
