@extends('layouts.app')

@section('title', 'Dashboard - ' . ($campaignName ?? 'CRM'))
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Dashboard')

@section('content')
@php
    $kpiHours = (int) config('dashboard.kpi_window_hours', 9);
    $monthTitle = now()->format('F Y');
@endphp
<div class="space-y-8">

    {{-- Welcome hero --}}
    <div class="md-hero">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-[var(--color-on-surface)]">Hello, {{ $user->full_name ?? $user->username }}</h2>
                <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">
                    Campaign: <span class="font-semibold text-[var(--color-primary)]">{{ $campaignName }}</span>
                </p>
            </div>
            <x-badge type="active">Online</x-badge>
        </div>
    </div>

    {{-- KPI + context stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 animate-stagger">
        <x-stat-card label="Calls ({{ $kpiHours }}h)"      :value="number_format($kpis['calls'] ?? 0)"          icon="phone" color="primary" />
        <x-stat-card label="Sales ({{ $kpiHours }}h)"     :value="number_format($kpis['sales'] ?? 0)"          icon="check-circle" color="success" />
        <x-stat-card label="Top agent ({{ $kpiHours }}h)" :value="$kpis['top_agent'] ?? '—'"                  icon="user" color="warning" />
        <x-stat-card label="Active Forms"                 :value="count($forms ?? [])"                        icon="document-text" color="info" />
        <x-stat-card label="Campaign"                     :value="strtoupper($campaign ?? '—')"               icon="building-office" color="info" />
    </div>

    {{-- Monthly activity chart --}}
    @if(!empty($monthlyActivity['labels']))
    <div class="grid grid-cols-1 gap-6 animate-stagger">
        <div class="chart-container">
            <p class="chart-title">Monthly activity — {{ $monthTitle }}</p>
            <div id="chart-monthly-activity" style="min-height: 260px;"></div>
        </div>
    </div>
    @endif

    {{-- Campaign forms --}}
    @if(!empty($forms))
    <div>
        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mb-4">Campaign Forms</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 animate-stagger">
            @foreach($forms as $formCode => $formConfig)
                <a href="{{ route('forms.show', ['type' => $formCode, 'campaign' => $campaign]) }}"
                   class="md-card p-5 flex items-center gap-4 group no-underline">
                    <div class="w-11 h-11 rounded-xl bg-[var(--color-primary-muted)] flex items-center justify-center shrink-0
                                border border-[var(--color-primary)] group-hover:scale-105 transition-transform">
                        <x-icon name="document-text" class="w-5 h-5 text-[var(--color-primary)]" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-[var(--color-on-surface)] truncate">{{ $formConfig['name'] ?? $formCode }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Submit new record</p>
                    </div>
                    <x-icon name="chevron-right" class="w-4 h-4 text-[var(--color-on-surface-dim)] group-hover:text-[var(--color-primary)] transition-colors shrink-0" />
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Quick links --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('records.index') }}" class="md-card p-4 flex items-center gap-3 no-underline group">
            <x-icon name="clipboard-document-list" class="w-5 h-5 text-[var(--color-primary)]" />
            <div class="flex-1">
                <h4 class="font-semibold text-[var(--color-on-surface)] text-sm">Call History</h4>
                <p class="text-xs text-[var(--color-on-surface-dim)]">View submitted records</p>
            </div>
            <x-icon name="chevron-right" class="w-4 h-4 text-[var(--color-on-surface-dim)] group-hover:text-[var(--color-primary)]" />
        </a>
        <a href="{{ route('attendance.index') }}" class="md-card p-4 flex items-center gap-3 no-underline group">
            <x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />
            <div class="flex-1">
                <h4 class="font-semibold text-[var(--color-on-surface)] text-sm">My Attendance</h4>
                <p class="text-xs text-[var(--color-on-surface-dim)]">View login history</p>
            </div>
            <x-icon name="chevron-right" class="w-4 h-4 text-[var(--color-on-surface-dim)] group-hover:text-[var(--color-primary)]" />
        </a>
    </div>

</div>
@endsection

@push('scripts')
@if(!empty($monthlyActivity['labels']))
<script>
(async () => {
    const ApexCharts = await window.ApexChartsLoader?.() ?? null;
    if (!ApexCharts) return;

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#a1a1aa' : '#52525b';
    const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

    window.__crmDashboardCharts = window.__crmDashboardCharts || {};
    Object.values(window.__crmDashboardCharts).forEach((c) => { try { c.destroy(); } catch (_) {} });
    window.__crmDashboardCharts = {};

    const monthlyEl = document.getElementById('chart-monthly-activity');
    if (monthlyEl) {
        const monthly = new ApexCharts(monthlyEl, {
            series: [{ name: 'Submissions', data: @json($monthlyActivity['values'] ?? []) }],
            chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 600 } },
            colors: ['#e91e8c'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: @json($monthlyActivity['labels'] ?? []), labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
            grid: { borderColor: gridColor, strokeDashArray: 3 },
            tooltip: { theme: isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            theme: { mode: isDark ? 'dark' : 'light' },
        });
        window.__crmDashboardCharts.monthly = monthly;
        monthly.render();
    }
})();
</script>
@endif
@endpush
