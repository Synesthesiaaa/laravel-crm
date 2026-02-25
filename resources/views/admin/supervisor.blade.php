@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('header-icon')<x-icon name="signal" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Supervisor Dashboard')

@section('header-actions')
    <span class="text-xs text-[var(--color-on-surface-dim)] flex items-center gap-1.5">
        <span class="inline-block w-2 h-2 rounded-full bg-[var(--color-success)] animate-pulse"></span>
        Live
    </span>
@endsection

@section('content')
<div x-data="supervisorDashboard()" x-init="init()" class="space-y-6">

    <x-page-header title="Supervisor Dashboard" description="Real-time agent monitoring."
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Supervisor' => null]" />

    {{-- Wallboard KPIs --}}
    <div class="wallboard animate-stagger">
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.agentsOnline">—</div>
            <div class="wallboard-label">Agents Online</div>
        </div>
        <div class="wallboard-metric" :class="{ 'wallboard-alert': stats.callsWaiting > 5 }">
            <div class="wallboard-value" x-text="stats.callsWaiting">—</div>
            <div class="wallboard-label">Calls Waiting</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.callsActive">—</div>
            <div class="wallboard-label">Active Calls</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.avgWaitTime">—</div>
            <div class="wallboard-label">Avg Wait (s)</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.todayTotal">—</div>
            <div class="wallboard-label">Today's Calls</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.slaPercent + '%'">—</div>
            <div class="wallboard-label">SLA %</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-[var(--color-border)]" role="tablist">
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'agents' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'agents'" role="tab">
            Agent Status Grid
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'performance' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'performance'" role="tab">
            Performance Metrics
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'wallboard' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'wallboard'" role="tab">
            Live Wallboard
        </button>
    </div>

    {{-- Agent Status Grid --}}
    <div x-show="tab === 'agents'" role="tabpanel">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">
                Agent Status — <span x-text="agents.length + ' agents'" class="text-[var(--color-primary)]"></span>
            </h3>
            <button @click="refresh()" class="btn-secondary text-xs">
                <span class="inline-flex" :class="loading ? 'animate-spin' : ''">
                    <x-icon name="arrow-path" class="w-3.5 h-3.5" />
                </span>
                Refresh
            </button>
        </div>
        <template x-if="loading && agents.length === 0">
            <div class="agent-status-grid">
                <template x-for="i in 6" :key="i">
                    <div class="agent-card">
                        <div class="skeleton skeleton-text w-24"></div>
                        <div class="skeleton skeleton-text w-16 mt-2"></div>
                        <div class="skeleton skeleton-text w-20 mt-1"></div>
                    </div>
                </template>
            </div>
        </template>
        <div class="agent-status-grid">
            <template x-for="agent in agents" :key="agent.id">
                <div class="agent-card"
                     :class="{
                         'agent-card-available': agent.status === 'available',
                         'agent-card-oncall':    agent.status === 'oncall',
                         'agent-card-break':     agent.status === 'break',
                         'agent-card-wrapup':    agent.status === 'wrapup',
                     }">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-sm text-[var(--color-on-surface)] truncate" x-text="agent.name"></span>
                        <span class="badge text-xs"
                              :class="{
                                  'badge-active':   agent.status === 'available',
                                  'badge-error':    agent.status === 'oncall',
                                  'badge-warning':  agent.status === 'break',
                                  'badge-pending':  agent.status === 'wrapup',
                                  'badge-inactive': agent.status === 'offline',
                              }"
                              x-text="agent.status_label">
                        </span>
                    </div>
                    <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="agent.current_call || '—'"></p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="agent.calls_today + ' calls today'"></span>
                        <span class="text-xs font-mono text-[var(--color-on-surface-muted)]" x-text="agent.since"></span>
                    </div>
                    {{-- Supervisor controls --}}
                    <div class="flex gap-1.5 mt-2" x-show="agent.status === 'oncall'">
                        <button class="btn-ghost text-xs px-2 py-1" @click="monitorAgent(agent)" title="Monitor (listen only)">
                            <x-icon name="eye" class="w-3 h-3" />
                            Monitor
                        </button>
                        <button class="btn-ghost text-xs px-2 py-1" @click="whisperAgent(agent)" title="Whisper (agent only)">
                            <x-icon name="microphone" class="w-3 h-3" />
                            Whisper
                        </button>
                    </div>
                </div>
            </template>
        </div>
        <template x-if="!loading && agents.length === 0">
            <div class="table-empty py-12">
                <x-icon name="users" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm font-medium">No agents currently online.</p>
            </div>
        </template>
    </div>

    {{-- Performance Metrics --}}
    <div x-show="tab === 'performance'" role="tabpanel">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="chart-container">
                <p class="chart-title">Agent Performance — Today</p>
                <div id="chart-agent-perf" style="min-height: 260px;"></div>
            </div>
            <div class="chart-container">
                <p class="chart-title">Call Volume — Hourly</p>
                <div id="chart-hourly" style="min-height: 260px;"></div>
            </div>
        </div>
        <div class="mt-6">
            <x-table.index caption="Agent performance table">
                <x-table.head :columns="[
                    ['label' => 'Agent'],
                    ['label' => 'Status'],
                    ['label' => 'Calls Today', 'align' => 'right'],
                    ['label' => 'Avg Handle (s)', 'align' => 'right'],
                    ['label' => 'Dispositions', 'align' => 'right'],
                    ['label' => 'Since'],
                ]" />
                <tbody>
                    <template x-for="agent in agents" :key="agent.id">
                        <tr>
                            <td x-text="agent.name" class="font-medium"></td>
                            <td>
                                <span class="badge"
                                      :class="{
                                          'badge-active':   agent.status === 'available',
                                          'badge-error':    agent.status === 'oncall',
                                          'badge-warning':  agent.status === 'break',
                                          'badge-inactive': agent.status === 'offline',
                                      }"
                                      x-text="agent.status_label"></span>
                            </td>
                            <td class="text-right font-semibold" x-text="agent.calls_today"></td>
                            <td class="text-right font-mono text-sm" x-text="agent.avg_handle + 's'"></td>
                            <td class="text-right" x-text="agent.dispositions"></td>
                            <td class="text-[var(--color-on-surface-dim)] text-sm" x-text="agent.since"></td>
                        </tr>
                    </template>
                </tbody>
            </x-table.index>
        </div>
    </div>

    {{-- Live Wallboard --}}
    <div x-show="tab === 'wallboard'" role="tabpanel">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6 animate-stagger">
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.agentsOnline">0</div>
                <div class="wallboard-label">Agents Online</div>
            </div>
            <div class="wallboard-metric" :class="{ 'wallboard-alert': stats.callsWaiting > 5 }">
                <div class="wallboard-value text-4xl" x-text="stats.callsWaiting">0</div>
                <div class="wallboard-label">In Queue</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.callsActive">0</div>
                <div class="wallboard-label">On Call</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.todayTotal">0</div>
                <div class="wallboard-label">Total Today</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.avgWaitTime + 's'">0s</div>
                <div class="wallboard-label">Avg Wait</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.slaPercent + '%'">0%</div>
                <div class="wallboard-label">SLA</div>
            </div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Real-time Call Volume</p>
            <div id="chart-realtime" style="min-height: 200px;"></div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
window.supervisorDashboard = function() {
    return {
        tab: 'agents',
        loading: false,
        agents: [],
        stats: {
            agentsOnline: 0, callsWaiting: 0, callsActive: 0,
            avgWaitTime: 0, todayTotal: 0, slaPercent: 0,
        },
        pollInterval: null,

        async init() {
            await this.refresh();
            const te = window.TelephonyEcho;
            if (te && te.initEcho && te.isBroadcastEnabled()) {
                te.initEcho();
                te.subscribeSupervisorChannel(
                    () => this.refresh(),
                    () => this.refresh()
                );
                this.pollInterval = setInterval(() => this.refresh(), 60000);
            } else {
                this.pollInterval = setInterval(() => this.refresh(), 15000);
            }
            this.$watch('tab', (t) => {
                if (t === 'performance' || t === 'wallboard') this.renderCharts();
            });
        },

        async refresh() {
            this.loading = true;
            try {
                const res = await window.axios.get('/api/supervisor/agents');
                this.agents = res.data.agents ?? this.mockAgents();
                this.stats  = res.data.stats  ?? this.mockStats();
            } catch {
                // Use mock data in dev / when API not available
                this.agents = this.mockAgents();
                this.stats  = this.mockStats();
            } finally {
                this.loading = false;
            }
        },

        mockAgents() {
            const statuses = [
                { s: 'available', l: 'Available' },
                { s: 'oncall',    l: 'On Call' },
                { s: 'break',     l: 'On Break' },
                { s: 'wrapup',    l: 'Wrap-up' },
                { s: 'offline',   l: 'Offline' },
            ];
            return [
                { id: 1, name: 'Maria Santos',  status: 'oncall',    status_label: 'On Call',   calls_today: 14, avg_handle: 185, dispositions: 12, since: '08:00', current_call: '+6391234' },
                { id: 2, name: 'Juan Cruz',     status: 'available', status_label: 'Available', calls_today: 9,  avg_handle: 210, dispositions: 8,  since: '08:30', current_call: null },
                { id: 3, name: 'Ana Reyes',     status: 'break',     status_label: 'Break',     calls_today: 11, avg_handle: 172, dispositions: 10, since: '09:00', current_call: null },
                { id: 4, name: 'Pedro Bautista',status: 'wrapup',    status_label: 'Wrap-up',   calls_today: 7,  avg_handle: 195, dispositions: 6,  since: '09:15', current_call: null },
                { id: 5, name: 'Rosa Mendoza',  status: 'oncall',    status_label: 'On Call',   calls_today: 16, avg_handle: 165, dispositions: 15, since: '08:00', current_call: '+6398765' },
                { id: 6, name: 'Marco Garcia',  status: 'offline',   status_label: 'Offline',   calls_today: 0,  avg_handle: 0,   dispositions: 0,  since: '—',     current_call: null },
            ];
        },

        mockStats() {
            return { agentsOnline: 5, callsWaiting: 3, callsActive: 2, avgWaitTime: 22, todayTotal: 57, slaPercent: 88 };
        },

        async monitorAgent(agent) {
            await window.axios.get('/api/vicidial/proxy', { params: { action: 'monitor', agent_id: agent.id } })
                .catch(() => {});
            Alpine.store('toast').info(`Monitoring ${agent.name}`);
        },

        async whisperAgent(agent) {
            await window.axios.get('/api/vicidial/proxy', { params: { action: 'whisper', agent_id: agent.id } })
                .catch(() => {});
            Alpine.store('toast').info(`Whispering to ${agent.name}`);
        },

        async renderCharts() {
            const ApexCharts = await window.ApexChartsLoader?.() ?? null;
            if (!ApexCharts) return;

            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const textColor = isDark ? '#a1a1aa' : '#52525b';
            const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

            const names   = this.agents.filter(a => a.status !== 'offline').map(a => a.name.split(' ')[0]);
            const callsArr= this.agents.filter(a => a.status !== 'offline').map(a => a.calls_today);

            if (this.tab === 'performance' && document.getElementById('chart-agent-perf')) {
                document.getElementById('chart-agent-perf').innerHTML = '';
                new ApexCharts(document.getElementById('chart-agent-perf'), {
                    series: [{ name: 'Calls Today', data: callsArr }],
                    chart: { type: 'bar', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
                    colors: ['#e91e8c'],
                    plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
                    xaxis: { categories: names, labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                }).render();

                // Hourly distribution (mock)
                document.getElementById('chart-hourly').innerHTML = '';
                new ApexCharts(document.getElementById('chart-hourly'), {
                    series: [{ name: 'Calls', data: [3,5,8,12,15,18,14,11,9,7,4,2] }],
                    chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
                    colors: ['#3b82f6'],
                    fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: .03 } },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: { categories: ['08','09','10','11','12','13','14','15','16','17','18','19'], labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                }).render();
            }

            if (this.tab === 'wallboard' && document.getElementById('chart-realtime')) {
                document.getElementById('chart-realtime').innerHTML = '';
                // Real-time sparkline (mock last 20 mins)
                const sparkData = Array.from({length:20}, () => Math.floor(Math.random() * 8 + 2));
                new ApexCharts(document.getElementById('chart-realtime'), {
                    series: [{ name: 'Calls/min', data: sparkData }],
                    chart: { type: 'line', height: 200, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: true, dynamicAnimation: { speed: 350 } } },
                    colors: ['#22c55e'],
                    stroke: { curve: 'smooth', width: 3 },
                    xaxis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                }).render();
            }
        },
    };
};
</script>
@endpush
