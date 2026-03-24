@extends('layouts.app')

@section('title', 'Management Dashboard')
@section('header-icon')<x-icon name="shield-check" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Management Dashboard')

@section('content')
<div class="space-y-8">

    <div class="md-hero">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-[var(--color-on-surface)]">Admin Control Center</h2>
                <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">
                    Campaign: <span class="font-semibold text-[var(--color-primary)]">{{ $campaignName }}</span>
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
    </div>

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
    @if(!empty($activityTrend['labels']))
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 chart-container">
            <p class="chart-title">Submission Activity — Last 30 days</p>
            <div id="admin-chart-activity" style="min-height: 240px;"></div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Top Agents</p>
            <div id="admin-chart-agents" style="min-height: 240px;"></div>
        </div>
    </div>
    @endif

    {{-- Admin navigation grid --}}
    <div>
        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mb-4">Admin Tools</h3>
        @php
        $adminLinks = [
            ['route' => 'admin.records.index',            'icon' => 'table-cells',             'label' => 'Records List',         'desc' => 'Call history & submissions'],
            ['route' => 'admin.data-master.index',        'icon' => 'list-bullet',             'label' => 'Data Master',          'desc' => 'CRUD form data records'],
            ['route' => 'admin.disposition-records.index','icon' => 'clipboard-document-list', 'label' => 'Disposition Records',  'desc' => 'Lead & disposition log'],
            ['route' => 'admin.disposition-codes.index',  'icon' => 'tag',                     'label' => 'Disposition Codes',    'desc' => 'Manage codes per campaign'],
            ['route' => 'admin.field-logic.index',        'icon' => 'cog-6-tooth',             'label' => 'Field Logic',          'desc' => 'Form field schemas'],
            ['route' => 'admin.extraction.index',         'icon' => 'arrow-down-tray',         'label' => 'Data Extraction',      'desc' => 'Export to CSV'],
            ['route' => 'admin.attendance.index',         'icon' => 'clock',                   'label' => 'Staff Attendance',     'desc' => 'Login event history'],
        ];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 animate-stagger">
            @foreach($adminLinks as $link)
                <a href="{{ route($link['route']) }}" class="md-card p-4 flex items-center gap-3 no-underline group">
                    <div class="w-10 h-10 rounded-lg bg-[var(--color-surface-2)] border border-[var(--color-border)] flex items-center justify-center shrink-0 group-hover:border-[var(--color-primary)] group-hover:bg-[var(--color-primary-muted)] transition-colors">
                        <x-icon :name="$link['icon']" class="w-5 h-5 text-[var(--color-on-surface-muted)] group-hover:text-[var(--color-primary)]" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-semibold text-[var(--color-on-surface)] text-sm">{{ $link['label'] }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] truncate">{{ $link['desc'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Super Admin section --}}
    @if($user->isSuperAdmin())
    <div>
        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mb-4">Super Admin</h3>
        @php
        $superLinks = [
            ['route' => 'admin.users.index',             'icon' => 'users',          'label' => 'User Access',       'desc' => 'Manage users & roles'],
            ['route' => 'admin.vicidial-servers.index',  'icon' => 'server',         'label' => 'ViciDial Servers',  'desc' => 'API & DB connections'],
            ['route' => 'admin.campaigns.index',         'icon' => 'building-office','label' => 'Campaigns',         'desc' => 'Manage campaigns'],
            ['route' => 'admin.forms.index',             'icon' => 'document-text',  'label' => 'Forms',             'desc' => 'Form definitions'],
            ['route' => 'admin.agent-screen.index',      'icon' => 'computer-desktop','label' => 'Agent Screen',     'desc' => 'Agent screen fields'],
            ['route' => 'admin.configuration',           'icon' => 'cog-6-tooth',    'label' => 'Configuration',     'desc' => 'System settings'],
        ];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 animate-stagger">
            @foreach($superLinks as $link)
                <a href="{{ route($link['route']) }}" class="md-card p-4 flex items-center gap-3 no-underline group">
                    <div class="w-10 h-10 rounded-lg bg-[var(--color-danger-muted)] border border-[var(--color-border)] flex items-center justify-center shrink-0 group-hover:border-[var(--color-danger)] transition-colors">
                        <x-icon :name="$link['icon']" class="w-5 h-5 text-[var(--color-danger-fg)]" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-semibold text-[var(--color-on-surface)] text-sm">{{ $link['label'] }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] truncate">{{ $link['desc'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
@if(!empty($activityTrend['labels']))
<script>
(async () => {
    const ApexCharts = await window.ApexChartsLoader?.() ?? null;
    if (!ApexCharts) return;

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#a1a1aa' : '#52525b';
    const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

    new ApexCharts(document.getElementById('admin-chart-activity'), {
        series: [{ name: 'Submissions', data: @json($activityTrend['values'] ?? []) }],
        chart: { type: 'area', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
        colors: ['#e91e8c'],
        fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .03 } },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { categories: @json($activityTrend['labels'] ?? []), labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
        grid: { borderColor: gridColor, strokeDashArray: 3 },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        dataLabels: { enabled: false },
        theme: { mode: isDark ? 'dark' : 'light' },
    }).render();

    const agentLabels = @json($topAgents['labels'] ?? []);
    if (agentLabels.length) {
        new ApexCharts(document.getElementById('admin-chart-agents'), {
            series: [{ name: 'Submissions', data: @json($topAgents['values'] ?? []) }],
            chart: { type: 'bar', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
            colors: ['#e91e8c'],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
            xaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontSize: '11px' }, maxWidth: 120 } },
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
