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

    {{-- Activity charts: daily / weekly / monthly --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 animate-stagger">
        <div class="chart-container">
            <p class="chart-title">Activity — last 24 hours</p>
            <div id="chart-daily-activity" class="w-full" style="min-height: 240px;"></div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Weekly activity — this week</p>
            <div id="chart-weekly-activity" class="w-full" style="min-height: 240px;"></div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Monthly activity — {{ $monthTitle }}</p>
            <div id="chart-monthly-activity" class="w-full" style="min-height: 240px;"></div>
        </div>
    </div>

    {{-- Top agents (month-to-date) --}}
    <div class="md-card overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Top agents — {{ $monthTitle }}</h3>
            <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Ranked by submissions, then sales count, then sale amount (from disposition data).</p>
        </div>
        <div class="md-table-wrap">
            @if(!empty($agentLeaderboard))
                <table>
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Agent</th>
                            <th class="text-right">Submissions</th>
                            <th class="text-right">Sales</th>
                            <th class="text-right">Sale amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agentLeaderboard as $idx => $row)
                            <tr>
                                <td class="text-[var(--color-on-surface-dim)]">{{ $idx + 1 }}</td>
                                <td class="font-medium text-[var(--color-on-surface)]">{{ $row['agent'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['submissions']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['sales_count']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['sales_amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No submission or sale activity this month yet.</p>
            @endif
        </div>
    </div>

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
<script>
(async () => {
    // Page scripts in the layout stack run during HTML parse; Vite app.js is deferred and runs after parse.
    // DOMContentLoaded fires only after deferred modules, so ApexChartsLoader exists then.
    if (document.readyState === 'loading') {
        await new Promise((resolve) => document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    }

    const ApexCharts = await window.ApexChartsLoader?.() ?? null;
    if (!ApexCharts) return;

    const main = document.getElementById('main-layout');
    if (!main) return;

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#a1a1aa' : '#52525b';
    const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

    window.__crmDashboardCharts = window.__crmDashboardCharts || {};
    Object.values(window.__crmDashboardCharts).forEach((c) => { try { c.destroy(); } catch (_) {} });
    window.__crmDashboardCharts = {};

    function mountAreaChart(elId, categories, values) {
        const el = document.getElementById(elId);
        if (!el || !main.contains(el)) return Promise.resolve();
        el.innerHTML = '';
        if (!categories?.length) return Promise.resolve();

        const chart = new ApexCharts(el, {
            series: [{ name: 'Submissions', data: values }],
            chart: { type: 'area', height: 240, width: '100%', toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 600 } },
            colors: ['#e91e8c'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories, labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
            grid: { borderColor: gridColor, strokeDashArray: 3 },
            tooltip: { theme: isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            theme: { mode: isDark ? 'dark' : 'light' },
        });
        window.__crmDashboardCharts[elId] = chart;
        return chart.render().then(() => { try { chart.resize(); } catch (_) {} });
    }

    if (!main.querySelector('#chart-monthly-activity')) return;

    await Promise.all([
        mountAreaChart('chart-daily-activity', @json($dailyActivity['labels'] ?? []), @json($dailyActivity['values'] ?? [])),
        mountAreaChart('chart-weekly-activity', @json($weeklyActivity['labels'] ?? []), @json($weeklyActivity['values'] ?? [])),
        mountAreaChart('chart-monthly-activity', @json($monthlyActivity['labels'] ?? []), @json($monthlyActivity['values'] ?? [])),
    ]);

    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
    window.resizeCrmDashboardCharts?.();
    requestAnimationFrame(() => window.resizeCrmDashboardCharts?.());
    // Sidebar / main-layout flex width often settles after ~280ms transition on first paint
    setTimeout(() => window.resizeCrmDashboardCharts?.(), 120);
    setTimeout(() => window.resizeCrmDashboardCharts?.(), 360);
})();
</script>
@endpush
