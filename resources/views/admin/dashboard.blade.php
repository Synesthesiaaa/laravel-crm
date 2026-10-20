@extends('layouts.app')

@section('title', 'Management Dashboard')
@section('header-icon')<x-icon name="shield-check" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Management Dashboard')

@section('content')
@php
    $seriesHasValues = static function (array $series): bool {
        foreach ((array) ($series['values'] ?? []) as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return true;
            }
        }

        return false;
    };
    $activityTrendHasValues = $seriesHasValues((array) ($activityTrend ?? []));
    $topAgentsHasValues = $seriesHasValues((array) ($topAgents ?? []));
@endphp
<div class="space-y-8">

    <div class="md-hero">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-[var(--color-on-surface)]">Admin Control Center</h2>
                <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">
                    Campaign: <span class="font-semibold text-[var(--color-action)]">{{ $campaignName }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <x-badge type="active">Live</x-badge>
                @if($user->isSuperAdmin())
                    <x-badge type="error">Super Admin</x-badge>
                @elseif($user->isAdmin())
                    <x-badge type="warning">Admin</x-badge>
                @else
                    <x-badge type="info">Team Leader</x-badge>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-5 flex flex-wrap items-end gap-3">
            <div class="min-w-[15rem] flex-1 max-w-md">
                <label for="admin-dashboard-campaign" class="form-label">Customize campaign</label>
                <select id="admin-dashboard-campaign" name="campaign" class="form-select w-full" onchange="this.form.submit()">
                    @foreach($campaigns as $campaignCode => $campaignConfig)
                        <option value="{{ $campaignCode }}" @selected($campaignCode === $campaign)>{{ $campaignConfig['name'] ?? $campaignCode }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">This selector only changes the dashboard being edited; it does not change an agent's active campaign.</p>
            </div>
            <noscript><button type="submit" class="btn-secondary">Load campaign</button></noscript>
        </form>
    </div>

    @include('admin.partials.dashboard-layout-editor')

    {{-- KPI stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 animate-stagger">
        @foreach($stats as $formCode => $stat)
            <x-stat-card
                :label="$stat['name']"
                :value="number_format($stat['count'])"
                icon="document-text"
                color="primary"
                :href="route('admin.data-master.index', ['type' => $formCode])" />
        @endforeach
        <x-stat-card
            label="System Users"
            :value="number_format($userCount)"
            icon="users"
            color="info"
            :href="$user->isSuperAdmin() ? route('admin.users.index') : null" />
    </div>

    {{-- Charts row --}}
    @if($activityTrendHasValues || $topAgentsHasValues || !empty($activityTrend['labels']) || !empty($topAgents['labels']))
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 chart-container">
            <p class="chart-title">Submission Activity — Last 30 days</p>
            @if($activityTrendHasValues)
                <div id="admin-chart-activity" aria-label="Submission activity chart"></div>
            @else
                <x-empty-state
                    icon="chart-bar"
                    title="No submission activity yet"
                    description="This chart will appear when the selected campaign records submissions in the last 30 days."
                    class="dashboard-chart-placeholder" />
            @endif
        </div>
        <div class="chart-container">
            <p class="chart-title">Top Agents</p>
            @if($topAgentsHasValues)
                <div id="admin-chart-agents" aria-label="Top agents chart"></div>
            @else
                <x-empty-state
                    icon="users"
                    title="No agent activity yet"
                    description="Agent rankings will appear when the selected campaign has qualifying submissions."
                    class="dashboard-chart-placeholder" />
            @endif
        </div>
    </div>
    @else
        <x-empty-state
            icon="chart-bar"
            title="No activity recorded yet"
            description="Management charts and rankings will appear when the selected campaign records activity."
            class="md-card" />
    @endif

    {{-- Admin navigation grid --}}
    @php
        $adminLinks = array_values(array_filter(
            \App\Support\AdminNavigation::adminItems(),
            static fn (array $item): bool => $item['route'] !== 'admin.dashboard',
        ));
    @endphp
    <x-admin.tool-grid title="Admin Tools" :items="$adminLinks" />

    {{-- Super Admin section --}}
    @if($user->isSuperAdmin())
        @php
            $superLinks = array_values(array_filter(
                \App\Support\AdminNavigation::superAdminItems(),
                static fn (array $item): bool => $item['route'] !== 'admin.agent-screen.index' || $agentScreenVisible,
            ));
        @endphp
        <x-admin.tool-grid title="Super Admin" :items="$superLinks" tone="danger" />
    @endif

</div>
@endsection

@push('scripts')
@if($activityTrendHasValues || $topAgentsHasValues)
<script>
(async () => {
    const scope = window.crmSoftNav?.currentScope?.() || window.location.pathname;
    const chartGroup = 'admin-dashboard';

    function destroyCharts() {
        window.crmCharts?.destroyGroup?.(chartGroup);
    }

    async function renderCharts() {
        destroyCharts();

        if (document.readyState === 'loading') {
            await new Promise((resolve) => document.addEventListener('DOMContentLoaded', resolve, { once: true }));
        }

        const ApexCharts = await window.ApexChartsLoader?.() ?? null;
        if (!ApexCharts) {
            return;
        }

        const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
        const textColor = isDark ? '#a1a1aa' : '#52525b';
        const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

        const activityEl = document.getElementById('admin-chart-activity');
        if (activityEl) {
            const activity = new ApexCharts(activityEl, {
                series: [{ name: 'Submissions', data: @json($activityTrend['values'] ?? []) }],
                chart: { type: 'area', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                colors: ['#e91e8c'],
                fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .03 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: @json($activityTrend['labels'] ?? []), labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                grid: { borderColor: gridColor, strokeDashArray: 3 },
                tooltip: { theme: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            window.crmCharts?.register?.(chartGroup, 'activity', activity);
            await activity.render();
        }

        const agentLabels = @json($topAgents['labels'] ?? []);
        const agentValues = @json($topAgents['values'] ?? []);
        const agentsEl = document.getElementById('admin-chart-agents');
        if (agentLabels.length && agentValues.some((value) => Number(value) > 0) && agentsEl) {
            const agents = new ApexCharts(agentsEl, {
                series: [{ name: 'Submissions', data: @json($topAgents['values'] ?? []) }],
                chart: { type: 'bar', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                colors: ['#e91e8c'],
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
                xaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                yaxis: { labels: { style: { colors: textColor, fontSize: '11px' }, maxWidth: 120 } },
                grid: { borderColor: gridColor, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                tooltip: { theme: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
                theme: { mode: isDark ? 'dark' : 'light' },
                categories: agentLabels,
            });
            window.crmCharts?.register?.(chartGroup, 'agents', agents);
            await agents.render();
        }

        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        window.crmCharts?.resizeGroup?.(chartGroup);
        requestAnimationFrame(() => window.crmCharts?.resizeGroup?.(chartGroup));
        setTimeout(() => window.crmCharts?.resizeGroup?.(chartGroup), 120);
        setTimeout(() => window.crmCharts?.resizeGroup?.(chartGroup), 360);
    }

    window.crmSoftNav?.register?.(scope, {
        beforeSwap: destroyCharts,
        afterSwap: () => {
            void renderCharts();
        },
    });

    if (!window.crmSoftNav?.isRehydrating?.()) {
        await renderCharts();
    }
})();
</script>
@endif
@endpush
