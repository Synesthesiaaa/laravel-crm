@extends('layouts.app')

@section('title', 'Dashboard - ' . ($campaignName ?? 'CRM'))
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Dashboard')

@section('content')
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

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-stagger">
        <x-stat-card label="Active Forms"    :value="count($forms ?? [])"                icon="document-text" color="primary" />
        <x-stat-card label="Campaign"        :value="strtoupper($campaign ?? '—')"       icon="building-office" color="info" />
        <x-stat-card label="Activity (14d)"  :value="array_sum($activityTrend['values'] ?? [])" icon="chart-bar" color="success" />
        <x-stat-card label="Top Agents"      :value="count($topAgents['labels'] ?? [])"  icon="users" color="warning" />
    </div>

    {{-- Charts --}}
    @if(!empty($activityTrend['labels']))
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-stagger">
        <div class="lg:col-span-2 chart-container">
            <p class="chart-title">Campaign Activity — Last 14 days</p>
            <div id="chart-activity" style="min-height: 220px;"></div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Top Agents</p>
            <div id="chart-agents" style="min-height: 220px;"></div>
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
@if(!empty($activityTrend['labels']))
<script>
(async () => {
    const ApexCharts = await window.ApexChartsLoader?.() ?? null;
    if (!ApexCharts) return;

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor   = isDark ? '#a1a1aa' : '#52525b';
    const borderColor = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.07)';
    const gridColor   = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';
    const surfaceColor = isDark ? '#1a1a1a' : '#fafafa';

    // Activity area chart
    new ApexCharts(document.getElementById('chart-activity'), {
        series: [{ name: 'Submissions', data: @json($activityTrend['values'] ?? []) }],
        chart: { type: 'area', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 600 } },
        colors: ['#e91e8c'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { categories: @json($activityTrend['labels'] ?? []), labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
        grid: { borderColor: gridColor, strokeDashArray: 3 },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        dataLabels: { enabled: false },
        theme: { mode: isDark ? 'dark' : 'light' },
    }).render();

    // Top agents bar chart
    const agentLabels = @json($topAgents['labels'] ?? []);
    if (agentLabels.length) {
        new ApexCharts(document.getElementById('chart-agents'), {
            series: [{ name: 'Submissions', data: @json($topAgents['values'] ?? []) }],
            chart: { type: 'bar', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
            colors: ['#e91e8c'],
            plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '60%' } },
            xaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } } },
            grid: { borderColor: gridColor, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            theme: { mode: isDark ? 'dark' : 'light' },
            categories: agentLabels,
        }).render();
    }
})();
</script>
@endif
@endpush
