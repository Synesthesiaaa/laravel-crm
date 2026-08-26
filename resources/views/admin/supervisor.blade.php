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
<div x-data="supervisorDashboard(@js($supervisorCampaign ?? session('campaign', '')))" x-init="init()" class="space-y-6">

    <x-page-header title="Supervisor Dashboard" description="Real-time agent monitoring."
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Supervisor' => null]" />

    <section class="md-card p-4" aria-labelledby="supervisor-routing-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p id="supervisor-routing-title" class="text-xs font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">
                    VICIdial Routing
                </p>
                <p class="text-sm font-medium text-[var(--color-on-surface)] mt-1"
                   x-text="routing.campaign_name ? routing.campaign_name + ' (' + routing.campaign_code + ')' : 'Loading campaign…'"></p>
            </div>
            <span x-show="routing.configured"
                  class="badge badge-active"
                  x-text="'Server: ' + (routing.server_name || 'Configured')"></span>
            <span x-show="!routing.configured && routing.campaign_code"
                  class="badge badge-warning">Server not configured</span>
        </div>
        @if(!empty($supervisorCampaigns))
            <div class="mt-3 max-w-sm">
                <label class="form-label" for="supervisor-campaign">CRM campaign</label>
                <select id="supervisor-campaign"
                        class="form-select"
                        x-model="selectedCampaign"
                        @change="changeCampaign()">
                    @foreach($supervisorCampaigns as $code => $config)
                        <option value="{{ $code }}">{{ $config['name'] ?? $code }} ({{ $code }})</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-[var(--color-on-surface-dim)] mt-1">
                    This CRM campaign selects the VICIdial server. The agent's VICIdial campaign is not used for routing.
                </p>
            </div>
        @endif
        <p x-show="routing.message"
           role="alert"
           aria-live="polite"
           class="text-xs text-[var(--color-danger)] mt-2"
           x-text="routing.message"></p>
    </section>

    <div x-show="errorMessage"
         x-transition.opacity
         class="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/5 px-4 py-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-[var(--color-danger)]">Live supervisor data unavailable</p>
                <p class="text-xs text-[var(--color-on-surface-muted)] mt-1" x-text="errorMessage"></p>
            </div>
            <button type="button" class="btn-secondary text-xs" @click="refresh()">Retry</button>
        </div>
    </div>

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

    <div class="md-card p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <div class="form-field">
                <label class="form-label">Recipient Type</label>
                <select class="form-select" x-model="notification.recipient_type">
                    <option value="USER">USER</option>
                    <option value="USER_GROUP">USER_GROUP</option>
                    <option value="CAMPAIGN">CAMPAIGN</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">Recipient</label>
                <input class="form-input" x-model="notification.recipient" placeholder="e.g. AGENTS or TESTCAMP or 6666" />
            </div>
            <div class="form-field md:col-span-2">
                <label class="form-label">Message</label>
                <input class="form-input" x-model="notification.text" placeholder="Notification text" />
            </div>
            <div class="form-field">
                <label class="inline-flex items-center gap-2 text-xs text-[var(--color-on-surface-muted)] mb-1">
                    <input type="checkbox" x-model="notification.confetti" />
                    Confetti
                </label>
                <button class="btn-secondary w-full text-xs"
                        :disabled="!routing.configured || notificationPending"
                        @click="sendNotification()">Send</button>
            </div>
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
            <div class="flex items-center gap-3">
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-show="lastRefreshAt" x-text="'Updated ' + lastRefreshAt"></span>
                <button @click="refresh()" class="btn-secondary text-xs">
                    <span class="inline-flex" :class="loading ? 'animate-spin' : ''">
                        <x-icon name="arrow-path" class="w-3.5 h-3.5" />
                    </span>
                    Refresh
                </button>
            </div>
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
                    <div class="text-[11px] text-[var(--color-on-surface-dim)] mt-1" x-text="'Vici: ' + (agent.vici_status || 'unknown') + ' · Queue: ' + (agent.queue_count ?? 0)"></div>
                    {{-- Supervisor controls --}}
                    <div class="flex gap-1.5 mt-2 flex-wrap">
                         <button class="btn-ghost text-xs px-2 py-1"
                                 :disabled="!routing.configured || isActionPending(agent, 'monitor')"
                                 @click="monitorAgent(agent)" title="Monitor (listen only)">
                            <x-icon name="eye" class="w-3 h-3" />
                            Monitor
                        </button>
                         <button class="btn-ghost text-xs px-2 py-1"
                                 :disabled="!routing.configured || isActionPending(agent, 'whisper')"
                                 @click="whisperAgent(agent)" title="Whisper (agent only)">
                            <x-icon name="microphone" class="w-3 h-3" />
                            Whisper
                        </button>
                         <button class="btn-ghost text-xs px-2 py-1"
                                 :disabled="!routing.configured || isActionPending(agent, 'pause')"
                                 @click="forcePause(agent)" title="Force pause agent">
                            <x-icon name="pause" class="w-3 h-3" />
                            Pause
                        </button>
                         <button class="btn-ghost text-xs px-2 py-1"
                                 :disabled="!routing.configured || isActionPending(agent, 'logout')"
                                 @click="forceLogout(agent)" title="Force logout agent">
                            <x-icon name="arrow-right-on-rectangle" class="w-3 h-3" />
                            Logout
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
const SUPERVISOR_CHART_GROUP = 'supervisor';

function destroySupervisorCharts() {
    window.crmCharts?.destroyGroup?.(SUPERVISOR_CHART_GROUP);
}

window.supervisorDashboard = function(initialCampaign = '') {
    return {
        tab: 'agents',
        loading: false,
        agents: [],
        routing: {
            campaign_code: '',
            campaign_name: '',
            configured: false,
            server_name: '',
            message: '',
        },
        actionPending: {},
        notificationPending: false,
        selectedCampaign: initialCampaign,
        stats: {
            agentsOnline: 0, callsWaiting: 0, callsActive: 0,
            avgWaitTime: 0, todayTotal: 0, slaPercent: 0,
        },
        pollInterval: null,
        _echoUnsubscribe: null,
        notification: {
            recipient_type: 'USER_GROUP',
            recipient: 'AGENTS',
            text: '',
            confetti: false,
        },
        errorMessage: '',
        lastRefreshAt: '',

        async init() {
            await this.refresh();
            const te = window.TelephonyEcho;
            if (te && te.initEcho && te.isBroadcastEnabled()) {
                te.initEcho();
                this._echoUnsubscribe = te.subscribeSupervisorChannel(
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

        destroy() {
            if (typeof this._echoUnsubscribe === 'function') {
                try {
                    this._echoUnsubscribe();
                } catch (_) {}
            }
            this._echoUnsubscribe = null;

            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            destroySupervisorCharts();
        },

        async refresh() {
            this.loading = true;
            this.errorMessage = '';
            try {
                const res = await window.axios.get('/api/supervisor/agents', {
                    params: { campaign: this.selectedCampaign || initialCampaign },
                });
                this.agents = res.data.agents ?? [];
                this.routing = res.data.routing ?? this.routing;
                this.selectedCampaign = this.routing.campaign_code || this.selectedCampaign;
                this.stats  = res.data.stats  ?? {
                    agentsOnline: 0, callsWaiting: 0, callsActive: 0,
                    avgWaitTime: 0, todayTotal: 0, slaPercent: 0,
                };
                this.lastRefreshAt = new Date().toLocaleTimeString();
            } catch (e) {
                this.agents = [];
                this.stats = {
                    agentsOnline: 0, callsWaiting: 0, callsActive: 0,
                    avgWaitTime: 0, todayTotal: 0, slaPercent: 0,
                };
                this.errorMessage = e.response?.data?.message || 'The supervisor API could not be reached. Check database, auth, and telephony services.';
            } finally {
                this.loading = false;
            }
        },

        changeCampaign() {
            const url = new URL(@json(route('admin.supervisor')), window.location.origin);
            if (this.selectedCampaign) {
                url.searchParams.set('campaign', this.selectedCampaign);
            }
            window.location.assign(url.toString());
        },

        actionKey(agent, action) {
            return `${agent.id}:${action}`;
        },

        isActionPending(agent, action) {
            return this.actionPending[this.actionKey(agent, action)] === true;
        },

        async runAgentAction(agent, action, url, successMessage) {
            if (!this.routing.configured) {
                Alpine.store('toast').error(this.routing.message || 'No VICIdial server is configured for this campaign.');
                return;
            }

            const key = this.actionKey(agent, action);
            this.actionPending[key] = true;
            try {
                const res = await window.axios.post(url, {
                    agent_user_id: agent.id,
                    campaign: agent.campaign_code || this.routing.campaign_code,
                });
                if (res.data?.success === false) {
                    throw new Error(res.data?.message || 'VICIdial action failed.');
                }
                Alpine.store('toast').info(`${successMessage} ${agent.name}`);
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || e.message || 'VICIdial action failed.');
            } finally {
                delete this.actionPending[key];
            }
        },

        async monitorAgent(agent) {
            await this.runAgentAction(agent, 'monitor', '/api/supervisor/monitor', 'Monitoring');
        },

        async whisperAgent(agent) {
            await this.runAgentAction(agent, 'whisper', '/api/supervisor/whisper', 'Whispering to');
        },

        async forcePause(agent) {
            await this.runAgentAction(agent, 'pause', '/api/supervisor/force-pause', 'Pause command sent to');
        },

        async forceLogout(agent) {
            await this.runAgentAction(agent, 'logout', '/api/supervisor/force-logout', 'Logout command sent to');
        },

        async sendNotification() {
            if (!this.notification.recipient) return;
            if (!this.routing.configured) {
                Alpine.store('toast').error(this.routing.message || 'No VICIdial server is configured for this campaign.');
                return;
            }
            this.notificationPending = true;
            try {
                const res = await window.axios.post('/api/supervisor/send-notification', {
                    recipient_type: this.notification.recipient_type,
                    recipient: this.notification.recipient,
                    campaign: this.routing.campaign_code,
                    notification_text: this.notification.text,
                    show_confetti: this.notification.confetti,
                });
                if (res.data?.success === false) {
                    throw new Error(res.data?.message || 'Failed to send notification.');
                }
                Alpine.store('toast').success('Notification sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to send notification.');
            } finally {
                this.notificationPending = false;
            }
        },

        async renderCharts() {
            destroySupervisorCharts();

            const ApexCharts = await window.ApexChartsLoader?.() ?? null;
            if (!ApexCharts) return;

            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const textColor = isDark ? '#a1a1aa' : '#52525b';
            const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

            const names   = this.agents.filter(a => a.status !== 'offline').map(a => a.name.split(' ')[0]);
            const callsArr= this.agents.filter(a => a.status !== 'offline').map(a => a.calls_today);
            const currentHour = new Date().getHours();
            const hourlyLabels = Array.from({ length: 12 }, (_, i) => String((currentHour - 11 + i + 24) % 24).padStart(2, '0'));
            const hourlyData = hourlyLabels.map((hour) => Number(hour) === currentHour ? Number(this.stats.todayTotal || 0) : 0);

            if (this.tab === 'performance' && document.getElementById('chart-agent-perf')) {
                document.getElementById('chart-agent-perf').innerHTML = '';
                const perfChart = new ApexCharts(document.getElementById('chart-agent-perf'), {
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
                });
                window.crmCharts?.register?.(SUPERVISOR_CHART_GROUP, 'agent-perf', perfChart);
                await perfChart.render();

                document.getElementById('chart-hourly').innerHTML = '';
                const hourlyChart = new ApexCharts(document.getElementById('chart-hourly'), {
                    series: [{ name: 'Calls', data: hourlyData }],
                    chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
                    colors: ['#3b82f6'],
                    fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: .03 } },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: { categories: hourlyLabels, labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                });
                window.crmCharts?.register?.(SUPERVISOR_CHART_GROUP, 'hourly', hourlyChart);
                await hourlyChart.render();
            }

            if (this.tab === 'wallboard' && document.getElementById('chart-realtime')) {
                document.getElementById('chart-realtime').innerHTML = '';
                const sparkData = Array.from({length:20}, (_, i) => i === 19 ? Number(this.stats.callsActive || 0) : 0);
                const realtimeChart = new ApexCharts(document.getElementById('chart-realtime'), {
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
                });
                window.crmCharts?.register?.(SUPERVISOR_CHART_GROUP, 'realtime', realtimeChart);
                await realtimeChart.render();
            }

            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            window.crmCharts?.resizeGroup?.(SUPERVISOR_CHART_GROUP);
        },
    };
};
</script>
@endpush
