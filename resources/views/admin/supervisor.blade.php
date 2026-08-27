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
                  class="badge badge-pending">Server not configured</span>
            <span x-show="routing.reporting_status && routing.reporting_status !== 'not_configured'"
                  role="status"
                  aria-live="polite"
                  class="badge"
                  :class="{
                      'badge-active': routing.reporting_status === 'live',
                      'badge-pending': routing.reporting_status === 'degraded',
                      'badge-error': routing.reporting_status === 'unavailable',
                      'badge-inactive': routing.reporting_status === 'stale',
                  }"
                  x-text="routing.reporting_status === 'live' ? 'VICIdial reports live' : (routing.reporting_status === 'degraded' ? 'VICIdial reports degraded' : (routing.reporting_status === 'stale' ? 'VICIdial reports stale' : 'VICIdial reports unavailable'))"></span>
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
           role="status"
           aria-live="polite"
           aria-atomic="true"
           class="text-xs mt-2"
           :class="routing.reporting_status === 'degraded' ? 'text-[var(--color-warning)]' : 'text-[var(--color-danger)]'"
           x-text="routing.message"></p>
        <p x-show="freshness.status === 'stale'"
           role="status"
           aria-live="polite"
           class="text-xs mt-2 text-[var(--color-warning)]"
           x-text="freshness.last_success_at ? 'Live data is stale. Last successful update: ' + formatAge(freshness.last_success_at) : 'Live data is stale. No successful update is available.'"></p>
        @if(auth()->user()?->isAdmin())
            <details x-show="Object.keys(routing.diagnostics || {}).length" class="mt-3 rounded-lg border border-[var(--color-border)] p-3">
                <summary class="cursor-pointer text-xs font-semibold text-[var(--color-on-surface)]">Technical source diagnostics</summary>
                <div class="mt-3 space-y-2">
                    <template x-for="(source, name) in (routing.diagnostics || {})" :key="name">
                        <div class="rounded border border-[var(--color-border)] p-2 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-[var(--color-on-surface)]" x-text="name"></span>
                                <span class="text-[var(--color-on-surface-muted)]" x-text="source.status + ' · ' + source.classification"></span>
                            </div>
                            <p class="mt-1 text-[var(--color-on-surface-dim)]" x-text="'HTTP ' + (source.http_status ?? '—') + ' · ' + (source.duration_ms ?? '—') + ' ms · ' + (source.parsed_rows ?? 0) + ' rows'"></p>
                            <p x-show="source.message" class="mt-1 text-[var(--color-warning)]" x-text="source.message"></p>
                        </div>
                    </template>
                </div>
            </details>
        @endif
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

    {{-- Operational KPIs --}}
    <div class="wallboard animate-stagger">
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.agentsOnline">—</div>
            <div class="wallboard-label">Agents Online</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.agentsAvailable">0</div>
            <div class="wallboard-label">Available</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.agentsOnCall">0</div>
            <div class="wallboard-label">On Call</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.agentsPaused">0</div>
            <div class="wallboard-label">Paused</div>
        </div>
        <div class="wallboard-metric" :class="{ 'wallboard-alert': ['WARNING', 'CRITICAL'].includes(stats.queue?.health) }">
            <div class="wallboard-value" x-text="stats.callsWaiting">—</div>
            <div class="wallboard-label">Calls Waiting</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="formatSeconds(stats.oldestWaitSeconds)">—</div>
            <div class="wallboard-label">Oldest Wait</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="formatSeconds(stats.avgWaitTime)">—</div>
            <div class="wallboard-label">Average Wait</div>
        </div>
    </div>
    <p class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--color-on-surface-dim)] -mt-4"
       role="status" aria-live="polite" aria-label="Supervisor metric data sources">
        <span>
            Live state:
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.realtimeSource === 'vicidial' ? 'VICIdial real-time report' : (stats.realtimeSource === 'mixed' ? 'VICIdial with CRM fallback' : 'CRM session fallback')"></span>
        </span>
        <span>
            Queue health:
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.queue?.health_label || 'Unknown'"></span>
        </span>
        <span>
            Agent timing:
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.performanceSource === 'vicidial' ? 'VICIdial agent stats' : (stats.performanceSource === 'mixed' ? 'VICIdial with CRM fallback' : 'CRM call-session fallback')"></span>
        </span>
        <span>
            Call totals:
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.callSource === 'vicidial' ? 'VICIdial daily report' : 'CRM call-session fallback'"></span>
        </span>
        <span x-show="lastRefreshAt" x-text="'Updated ' + lastRefreshAt"></span>
    </p>

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
        <button id="supervisor-tab-agents" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'agents' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'agents'" role="tab" :aria-selected="tab === 'agents'" aria-controls="supervisor-panel-agents">
            Agent Status
        </button>
        <button id="supervisor-tab-queue" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'queue' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'queue'" role="tab" :aria-selected="tab === 'queue'" aria-controls="supervisor-panel-queue">
            Queue Monitor
        </button>
        <button id="supervisor-tab-wallboard" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'wallboard' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'wallboard'" role="tab" :aria-selected="tab === 'wallboard'" aria-controls="supervisor-panel-wallboard">
            Live Wallboard
        </button>
    </div>

    {{-- Agent Status --}}
    <div id="supervisor-panel-agents" x-show="tab === 'agents'" role="tabpanel" aria-labelledby="supervisor-tab-agents">
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
                         'agent-card-available': agent.state === 'AVAILABLE',
                         'agent-card-oncall':    ['ON_CALL', 'RINGING'].includes(agent.state),
                         'agent-card-break':     agent.state === 'PAUSED',
                         'agent-card-wrapup':    agent.state === 'QUEUE',
                     }">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-sm text-[var(--color-on-surface)] truncate" x-text="agent.name"></span>
                        <span class="badge text-xs"
                              :class="{
                                  'badge-active':   agent.state === 'AVAILABLE',
                                  'badge-error':    ['ON_CALL', 'RINGING'].includes(agent.state),
                                  'badge-warning':  agent.state === 'PAUSED',
                                  'badge-pending':  agent.state === 'QUEUE',
                                  'badge-inactive': ['OFFLINE', 'UNKNOWN'].includes(agent.state),
                              }"
                              x-text="agent.status_label">
                        </span>
                    </div>
                    <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="agent.current_call ? (agent.current_call.phone_number + ' · ' + formatSeconds(agent.current_call.duration)) : (agent.state === 'AVAILABLE' ? 'Ready for next call' : '—')"></p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="agent.calls_since_login + ' calls since login'"></span>
                        <span class="text-xs font-mono text-[var(--color-on-surface-muted)]" x-text="agent.state_duration_seconds == null ? '—' : formatSeconds(agent.state_duration_seconds)"></span>
                    </div>
                    <div class="text-[11px] text-[var(--color-on-surface-dim)] mt-1" x-text="'State: ' + agent.state_label + ' · Vici: ' + (agent.vici_status || 'unknown')"></div>
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

    {{-- Queue Monitor --}}
    <div id="supervisor-panel-queue" x-show="tab === 'queue'" role="tabpanel" aria-labelledby="supervisor-tab-queue">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="md-card p-4">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Queue health</p>
                <p class="mt-2 text-xl font-bold" :class="stats.queue?.health === 'CRITICAL' ? 'text-[var(--color-danger)]' : (stats.queue?.health === 'WARNING' ? 'text-[var(--color-warning)]' : (stats.queue?.health === 'HEALTHY' ? 'text-[var(--color-success)]' : 'text-[var(--color-on-surface-muted)]'))" x-text="stats.queue?.health_label || 'Unknown'"></p>
            </div>
            <div class="md-card p-4">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Calls waiting</p>
                <p class="mt-2 text-xl font-bold" x-text="stats.callsWaiting ?? '—'"></p>
            </div>
            <div class="md-card p-4">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Oldest wait</p>
                <p class="mt-2 text-xl font-bold" x-text="formatSeconds(stats.oldestWaitSeconds)"></p>
            </div>
            <div class="md-card p-4">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Average wait</p>
                <p class="mt-2 text-xl font-bold" x-text="formatSeconds(stats.avgWaitTime)"></p>
            </div>
            <div class="md-card p-4">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Available agents</p>
                <p class="mt-2 text-xl font-bold" x-text="stats.agentsAvailable"></p>
            </div>
            <div class="md-card p-4">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Active calls</p>
                <p class="mt-2 text-xl font-bold" x-text="stats.callsActive ?? '—'"></p>
            </div>
            <div class="md-card p-4 col-span-2">
                <p class="text-xs text-[var(--color-on-surface-dim)]">Queue window</p>
                <p class="mt-2 text-sm font-medium" x-text="(stats.queue?.window_minutes || 15) + ' minute rolling view · ' + (stats.queue?.abandoned_last_15m == null ? 'Abandoned calls unavailable' : stats.queue.abandoned_last_15m + ' abandoned')"></p>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="chart-container">
                <p class="chart-title">Queue pressure — last 15 minutes</p>
                <div id="chart-queue-pressure" style="min-height: 260px;"></div>
            </div>
            <div class="md-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Operational attention</h3>
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Conditions that may need supervisor intervention.</p>
                    </div>
                    <span class="badge" :class="stats.queue?.health === 'CRITICAL' ? 'badge-error' : (stats.queue?.health === 'WARNING' ? 'badge-warning' : (stats.queue?.health === 'HEALTHY' ? 'badge-active' : 'badge-inactive'))" x-text="stats.queue?.health_label || 'Unknown'"></span>
                </div>
                <ul class="mt-4 space-y-2 text-sm" x-show="stats.queue?.reasons?.length">
                    <template x-for="reason in (stats.queue?.reasons || [])" :key="reason">
                        <li class="flex gap-2 text-[var(--color-on-surface-muted)]"><span aria-hidden="true">•</span><span x-text="reason"></span></li>
                    </template>
                </ul>
                <p class="mt-4 text-sm text-[var(--color-on-surface-dim)]" x-show="!stats.queue?.reasons?.length">
                    No active queue exceptions reported.
                </p>
            </div>
        </div>
    </div>

    {{-- Live Wallboard --}}
    <div x-show="tab === 'wallboard'" role="tabpanel">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6 animate-stagger">
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.agentsOnline">0</div>
                <div class="wallboard-label">Agents Online</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.agentsAvailable">0</div>
                <div class="wallboard-label">Available</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.agentsOnCall">0</div>
                <div class="wallboard-label">On Call</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.agentsPaused">0</div>
                <div class="wallboard-label">Paused</div>
            </div>
            <div class="wallboard-metric" :class="{ 'wallboard-alert': ['WARNING', 'CRITICAL'].includes(stats.queue?.health) }">
                <div class="wallboard-value text-4xl" x-text="stats.callsWaiting">0</div>
                <div class="wallboard-label">In Queue</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="formatSeconds(stats.oldestWaitSeconds)">—</div>
                <div class="wallboard-label">Oldest Wait</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="formatSeconds(stats.avgWaitTime)">—</div>
                <div class="wallboard-label">Average Wait</div>
            </div>
        </div>
        <p class="text-xs text-[var(--color-on-surface-dim)] -mt-2 mb-4" role="status" aria-live="polite">
            Live state from
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.realtimeSource === 'vicidial' ? 'VICIdial real-time report' : (stats.realtimeSource === 'mixed' ? 'VICIdial with CRM fallback' : 'CRM session fallback')"></span>
            · agent timing from
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.performanceSource === 'vicidial' ? 'VICIdial agent stats' : (stats.performanceSource === 'mixed' ? 'VICIdial with CRM fallback' : 'CRM call-session fallback')"></span>
            · call totals from
            <span class="font-medium text-[var(--color-on-surface-muted)]"
                  x-text="stats.callSource === 'vicidial' ? 'VICIdial daily report' : 'CRM call-session fallback'"></span>
        </p>
        <div class="chart-container">
            <p class="chart-title">Calls waiting — last 15 minutes</p>
            <div id="chart-realtime" style="min-height: 200px;"></div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const SUPERVISOR_CHART_GROUP = 'supervisor';
const SUPERVISOR_POLL_SECONDS = Math.max(1, @json((int) config('vicidial.supervisor.poll_seconds', 15)));
const SUPERVISOR_QUEUE_HISTORY_LENGTH = Math.max(20, Math.ceil((15 * 60) / SUPERVISOR_POLL_SECONDS));

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
            reporting_status: 'not_configured',
            message: '',
            diagnostics: {},
        },
        freshness: {
            status: 'offline',
            last_success_at: null,
            stale_after_seconds: 45,
        },
        notificationPending: false,
        refreshInFlight: false,
        queueHistory: [],
        selectedCampaign: initialCampaign,
        stats: {
            agentsOnline: 0, callsWaiting: 0, callsActive: 0,
            agentsAvailable: 0, agentsOnCall: 0, agentsPaused: 0,
            avgWaitTime: null, oldestWaitSeconds: null, avgHandleTime: null, todayTotal: 0,
            callsAnswered: 0, answerRate: 0, slaPercent: 0, callsByHour: {},
            queue: { health: 'UNKNOWN', health_label: 'Unknown', reasons: [], window_minutes: 15 },
            callSource: 'crm', realtimeSource: 'crm', performanceSource: 'crm',
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

        formatAge(value) {
            if (!value) return 'unknown';
            const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000));
            if (seconds < 60) return `${seconds}s ago`;
            return `${Math.floor(seconds / 60)}m ${String(seconds % 60).padStart(2, '0')}s ago`;
        },

        formatSeconds(value) {
            if (value === null || value === undefined || value === '') return '—';
            const seconds = Math.max(0, Math.round(Number(value)));
            if (seconds < 60) return `${seconds}s`;
            return `${Math.floor(seconds / 60)}m ${String(seconds % 60).padStart(2, '0')}s`;
        },

        async init() {
            await this.refresh();
            const te = window.TelephonyEcho;
            if (te && te.initEcho && te.isBroadcastEnabled()) {
                te.initEcho();
                this._echoUnsubscribe = te.subscribeSupervisorChannel(
                    () => this.refresh(),
                    () => this.refresh()
                );
                this.pollInterval = setInterval(() => this.refresh(), SUPERVISOR_POLL_SECONDS * 1000);
            } else {
                this.pollInterval = setInterval(() => this.refresh(), SUPERVISOR_POLL_SECONDS * 1000);
            }
            this.$watch('tab', (t) => {
                if (t === 'queue' || t === 'wallboard') this.renderCharts();
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
            if (this.refreshInFlight) return;

            this.refreshInFlight = true;
            this.loading = true;
            this.errorMessage = '';
            try {
                const res = await window.axios.get('/api/supervisor/agents', {
                    params: { campaign: this.selectedCampaign || initialCampaign },
                });
                this.agents = res.data.agents ?? [];
                this.routing = res.data.routing ?? this.routing;
                this.freshness = res.data.freshness ?? this.freshness;
                this.selectedCampaign = this.routing.campaign_code || this.selectedCampaign;
                this.stats  = res.data.stats  ?? {
                    agentsOnline: 0, callsWaiting: 0, callsActive: 0,
                    agentsAvailable: 0, agentsOnCall: 0, agentsPaused: 0,
                    avgWaitTime: null, oldestWaitSeconds: null, avgHandleTime: null, todayTotal: 0,
                    callsAnswered: 0, answerRate: 0, slaPercent: 0, callsByHour: {},
                    queue: { health: 'UNKNOWN', health_label: 'Unknown', reasons: [], window_minutes: 15 },
                    callSource: 'crm', realtimeSource: 'crm', performanceSource: 'crm',
                };
                this.queueHistory = [
                    ...this.queueHistory,
                    this.stats.callsWaiting == null ? null : Number(this.stats.callsWaiting),
                ].slice(-SUPERVISOR_QUEUE_HISTORY_LENGTH);
                this.lastRefreshAt = res.data.stats?.updatedAt
                    ? new Date(res.data.stats.updatedAt).toLocaleTimeString()
                    : new Date().toLocaleTimeString();
                if (this.tab === 'queue' || this.tab === 'wallboard') {
                    this.renderCharts();
                }
            } catch (e) {
                this.errorMessage = e.response?.data?.message || 'The supervisor API could not be reached. Check database, auth, and telephony services.';
                if (this.lastRefreshAt) {
                    this.freshness.status = 'stale';
                    this.routing.reporting_status = 'stale';
                }
            } finally {
                this.loading = false;
                this.refreshInFlight = false;
            }
        },

        changeCampaign() {
            const url = new URL(@json(route('admin.supervisor')), window.location.origin);
            if (this.selectedCampaign) {
                url.searchParams.set('campaign', this.selectedCampaign);
            }
            window.location.assign(url.toString());
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

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const queueData = this.queueHistory.map(value => value == null ? 0 : value);
            const queueLabels = this.queueHistory.map((_, index) => `${(index + 1) * SUPERVISOR_POLL_SECONDS}s`);

            if (this.tab === 'queue' && document.getElementById('chart-queue-pressure')) {
                document.getElementById('chart-queue-pressure').innerHTML = '';
                const queueChart = new ApexCharts(document.getElementById('chart-queue-pressure'), {
                    series: [{ name: 'Calls waiting', data: queueData }],
                    chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: !reduceMotion } },
                    colors: ['#f59e0b'],
                    fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: .03 } },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: { categories: queueLabels, labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                });
                window.crmCharts?.register?.(SUPERVISOR_CHART_GROUP, 'queue-pressure', queueChart);
                await queueChart.render();
            }

            if (this.tab === 'wallboard' && document.getElementById('chart-realtime')) {
                document.getElementById('chart-realtime').innerHTML = '';
                const sparkData = [
                    ...Array(Math.max(0, SUPERVISOR_QUEUE_HISTORY_LENGTH - this.queueHistory.length)).fill(0),
                    ...this.queueHistory.map(value => value == null ? 0 : value),
                ];
                const realtimeChart = new ApexCharts(document.getElementById('chart-realtime'), {
                    series: [{ name: 'Calls waiting', data: sparkData }],
                    chart: { type: 'line', height: 200, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: !reduceMotion, dynamicAnimation: { speed: 350 } } },
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
