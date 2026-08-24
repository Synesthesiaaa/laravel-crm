@extends('layouts.app')

@section('title', 'Telephony Reports')
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Telephony Reports')

@section('content')
<div x-data="telephonyReports()" x-init="init()" class="space-y-6">
    <x-page-header
        title="Telephony Reports"
        description="Dashboard-style VICIdial reporting for higher roles."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => null]" />

    <template x-if="errorMessage">
        <div class="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/5 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[var(--color-danger)]">Report data could not be loaded</p>
                    <p class="text-xs text-[var(--color-on-surface-muted)] mt-1" x-text="errorMessage"></p>
                </div>
                <button type="button" class="btn-secondary text-xs" @click="refreshAll()">Retry</button>
            </div>
        </div>
    </template>

    <div class="md-hero">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div class="space-y-1">
                <h2 class="text-xl font-bold text-[var(--color-on-surface)]">Operational Snapshot</h2>
                <p class="text-[var(--color-on-surface-muted)] text-sm">
                    Campaign: <span class="font-semibold text-[var(--color-primary)]" x-text="dashboard.overview.campaign"></span>
                </p>
                <p class="text-xs text-[var(--color-on-surface-dim)]">
                    Report range:
                    <span class="font-medium text-[var(--color-on-surface-muted)]" x-text="filters.query_date"></span>
                    to
                    <span class="font-medium text-[var(--color-on-surface-muted)]" x-text="filters.end_date"></span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-badge type="active">Live</x-badge>
                <button class="btn-secondary text-xs" @click="refreshAll()" x-bind:disabled="loading">
                    <span class="inline-flex" x-bind:class="loading ? 'animate-spin' : ''">
                        <x-icon name="arrow-path" class="w-4 h-4" />
                    </span>
                    <span x-text="loading ? 'Loading...' : 'Refresh Reports'">Refresh Reports</span>
                </button>
            </div>
        </div>
    </div>

    <div class="md-card p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div class="form-field">
                <label class="form-label">Campaigns</label>
                <input class="form-input" x-model="filters.campaigns" placeholder="---ALL--- or TESTCAMP" />
            </div>
            <div class="form-field">
                <label class="form-label">Date Start</label>
                <input class="form-input" type="date" x-model="filters.query_date" />
            </div>
            <div class="form-field">
                <label class="form-label">Date End</label>
                <input class="form-input" type="date" x-model="filters.end_date" />
            </div>
            <div class="form-field">
                <label class="form-label">Disposition Scope</label>
                <select class="form-input" x-model="filters.disposition_scope">
                    <template x-for="option in dispositionScopeOptions" :key="option.value">
                        <option :value="option.value" x-text="option.label"></option>
                    </template>
                </select>
                <p class="mt-1 text-[11px] text-[var(--color-on-surface-dim)]">
                    System codes:
                    <span class="font-medium text-[var(--color-on-surface-muted)]" x-text="systemDispositionCodes.length ? systemDispositionCodes.join(', ') : 'None configured'"></span>
                </p>
            </div>
            <div class="form-field flex items-end">
                <button class="btn-primary w-full" @click="refreshAll()" x-bind:disabled="loading">
                    <span class="inline-flex" x-bind:class="loading ? 'animate-spin' : ''">
                        <x-icon name="arrow-path" class="w-4 h-4" />
                    </span>
                    <span x-text="loading ? 'Loading...' : 'Refresh Reports'">Refresh Reports</span>
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4 animate-stagger">
        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Campaign</p>
                    <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)] truncate" x-text="dashboard.overview.campaign"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-primary-muted)] flex items-center justify-center shrink-0">
                    <x-icon name="building-office" class="w-5 h-5 text-[var(--color-primary)]" />
                </div>
            </div>
        </div>

        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Total Calls</p>
                    <p class="mt-2 text-2xl font-bold text-[var(--color-on-surface)]" x-text="formatNumber(dashboard.overview.totalCalls)"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-surface-2)] flex items-center justify-center shrink-0">
                    <x-icon name="phone" class="w-5 h-5 text-[var(--color-primary)]" />
                </div>
            </div>
        </div>

        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Answered</p>
                    <p class="mt-2 text-2xl font-bold text-[var(--color-on-surface)]" x-text="formatNumber(dashboard.overview.answeredCalls)"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-success)]/10 flex items-center justify-center shrink-0">
                    <x-icon name="check-circle" class="w-5 h-5 text-[var(--color-success)]" />
                </div>
            </div>
        </div>

        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Answer Rate</p>
                    <p class="mt-2 text-2xl font-bold text-[var(--color-on-surface)]" x-text="formatPercent(dashboard.overview.answerRate)"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-info)]/10 flex items-center justify-center shrink-0">
                    <x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-info)]" />
                </div>
            </div>
        </div>

        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Top Agent</p>
                    <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)] truncate" x-text="dashboard.overview.topAgent"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-warning)]/10 flex items-center justify-center shrink-0">
                    <x-icon name="user" class="w-5 h-5 text-[var(--color-warning)]" />
                </div>
            </div>
        </div>

        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Top Disposition</p>
                    <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)] truncate" x-text="dashboard.overview.topDisposition"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-danger)]/10 flex items-center justify-center shrink-0">
                    <x-icon name="tag" class="w-5 h-5 text-[var(--color-danger)]" />
                </div>
            </div>
        </div>

        <div class="md-card p-4 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Active Agents</p>
                    <p class="mt-2 text-2xl font-bold text-[var(--color-on-surface)]" x-text="formatNumber(dashboard.overview.activeAgents)"></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-[var(--color-surface-2)] flex items-center justify-center shrink-0">
                    <x-icon name="users" class="w-5 h-5 text-[var(--color-primary)]" />
                </div>
            </div>
        </div>
    </div>

    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Call Status Dashboard</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">Hourly activity and status mix for the selected campaign/date range.</p>
            </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="'Rows loaded: ' + dashboard.status.rows.length"></p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container">
                <p class="chart-title">Hourly volume</p>
                <div x-show="dashboard.status.hourlyLabels.length" id="chart-status-hourly" class="w-full chart-host" style="min-height: 280px;"></div>
                <div x-show="!dashboard.status.hourlyLabels.length" class="table-empty py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                    No hourly data yet.
                </div>
            </div>
            <div class="chart-container">
                <p class="chart-title">Status mix</p>
                <div x-show="dashboard.status.statusLabels.length" id="chart-status-mix" class="w-full chart-host" style="min-height: 280px;"></div>
                <div x-show="!dashboard.status.statusLabels.length" class="table-empty py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                    No status breakdown yet.
                </div>
            </div>
        </div>

        <div class="md-card overflow-hidden">
            <div class="px-5 py-4 border-b border-[var(--color-border)] flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Call status breakdown</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Each row is grouped by campaign or ingroup.</p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.status.rows.length + ' rows'"></span>
            </div>
            <div class="md-table-wrap">
                <x-table.index caption="Call status breakdown table">
                    <x-table.head :columns="[
                        ['label' => 'Campaign / In-Group'],
                        ['label' => 'Total'],
                        ['label' => 'Answered'],
                        ['label' => 'Answer Rate', 'align' => 'right'],
                        ['label' => 'Top Status'],
                        ['label' => 'Peak Hour', 'align' => 'right'],
                    ]" />
                    <tbody>
                        <template x-for="row in dashboard.status.rows" :key="row.key">
                            <tr>
                                <td class="font-medium text-[var(--color-on-surface)]" x-text="row.label"></td>
                                <td class="tabular-nums" x-text="formatNumber(row.total)"></td>
                                <td class="tabular-nums" x-text="formatNumber(row.answered)"></td>
                                <td class="text-right tabular-nums" x-text="formatPercent(row.answerRate)"></td>
                                <td x-text="row.topStatus"></td>
                                <td class="text-right tabular-nums" x-text="row.peakHourLabel"></td>
                            </tr>
                        </template>
                    </tbody>
                </x-table.index>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Agent Performance</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">Readable agent activity summaries built from VICIdial export rows.</p>
            </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="'Agents loaded: ' + dashboard.agents.rows.length"></p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container">
                <p class="chart-title">Calls by agent</p>
                <div x-show="dashboard.agents.callsLabels.length" id="chart-agent-calls" class="w-full chart-host" style="min-height: 280px;"></div>
                <div x-show="!dashboard.agents.callsLabels.length" class="table-empty py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                    No agent rows yet.
                </div>
            </div>
            <div class="chart-container">
                <p class="chart-title">Talk time balance</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="md-card p-4">
                        <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Total talk</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)]" x-text="dashboard.agents.summary.totalTalkTime"></p>
                    </div>
                    <div class="md-card p-4">
                        <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Avg talk / call</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)]" x-text="dashboard.agents.summary.avgTalkTime"></p>
                    </div>
                    <div class="md-card p-4">
                        <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Total pause</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)]" x-text="dashboard.agents.summary.totalPauseTime"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="md-card overflow-hidden">
            <div class="px-5 py-4 border-b border-[var(--color-border)] flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Agent performance table</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Sorted by calls in the selected range.</p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.agents.summary.agentCount + ' agents'"></span>
            </div>
            <div class="md-table-wrap">
                <x-table.index caption="Agent performance table">
                    <x-table.head :columns="[
                        ['label' => 'Agent'],
                        ['label' => 'Group'],
                        ['label' => 'Calls', 'align' => 'right'],
                        ['label' => 'Talk Time', 'align' => 'right'],
                        ['label' => 'Avg Talk', 'align' => 'right'],
                        ['label' => 'Wait Time', 'align' => 'right'],
                        ['label' => 'Pause %', 'align' => 'right'],
                    ]" />
                    <tbody>
                        <template x-for="row in dashboard.agents.rows" :key="row.key">
                            <tr>
                                <td>
                                    <div class="font-medium text-[var(--color-on-surface)]" x-text="row.full_name || row.user"></div>
                                    <div class="text-xs text-[var(--color-on-surface-dim)]" x-text="row.user"></div>
                                </td>
                                <td x-text="row.user_group"></td>
                                <td class="text-right tabular-nums" x-text="formatNumber(row.calls)"></td>
                                <td class="text-right tabular-nums" x-text="row.total_talk_time"></td>
                                <td class="text-right tabular-nums" x-text="row.avg_talk_time"></td>
                                <td class="text-right tabular-nums" x-text="row.total_wait_time"></td>
                                <td class="text-right tabular-nums" x-text="row.pause_pct"></td>
                            </tr>
                        </template>
                    </tbody>
                </x-table.index>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Disposition Breakdown</h3>
                    <p class="text-xs text-[var(--color-on-surface-dim)]">
                        Disposition totals and percentages for the selected report window.
                        <span class="font-medium text-[var(--color-on-surface-muted)]" x-text="'Scope: ' + dashboard.scopeLabel"></span>
                    </p>
                </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="'Disposition rows: ' + dashboard.dispo.rows.length"></p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container">
                <p class="chart-title">Disposition mix</p>
                <div x-show="dashboard.dispo.labels.length" id="chart-dispo-breakdown" class="w-full chart-host" style="min-height: 280px;"></div>
                <div x-show="!dashboard.dispo.labels.length" class="table-empty py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                    No disposition data yet.
                </div>
            </div>
            <div class="md-card p-4 space-y-3">
                <p class="chart-title mb-0">Report totals</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="md-card p-4">
                        <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Total Calls</p>
                        <p class="mt-2 text-2xl font-bold text-[var(--color-on-surface)]" x-text="formatNumber(dashboard.dispo.summary.totalCalls)"></p>
                    </div>
                    <div class="md-card p-4">
                        <p class="text-xs uppercase tracking-widest text-[var(--color-on-surface-dim)]">Top Disposition</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)] truncate" x-text="dashboard.dispo.summary.topDisposition"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="md-card overflow-hidden">
            <div class="px-5 py-4 border-b border-[var(--color-border)] flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Disposition table</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Each row mirrors the VICIdial export but with the total row kept in the debug area.</p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.dispo.rows.length + ' campaigns'"></span>
            </div>
            <div class="md-table-wrap">
                <x-table.index caption="Disposition table">
                    <x-table.head :columns="[
                        ['label' => 'Campaign / In-Group'],
                        ['label' => 'Total Calls', 'align' => 'right'],
                        ['label' => 'Top Disposition'],
                        ['label' => 'Breakdown'],
                    ]" />
                    <tbody>
                        <template x-for="row in dashboard.dispo.rows" :key="row.key">
                            <tr>
                                <td class="font-medium text-[var(--color-on-surface)]" x-text="row.label"></td>
                                <td class="text-right tabular-nums" x-text="formatNumber(row.totalCalls)"></td>
                                <td x-text="row.topDisposition"></td>
                                <td class="text-sm text-[var(--color-on-surface-dim)]" x-text="row.breakdownSummary"></td>
                            </tr>
                        </template>
                    </tbody>
                </x-table.index>
            </div>
        </div>
    </section>

    <details class="md-card p-4">
        <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Debug / Raw VICIdial Output</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Collapsed by default. Open this only when the dashboard needs diagnosis.</p>
            </div>
            <span class="text-xs text-[var(--color-on-surface-dim)]">
                <span x-text="dashboard.status.rows.length"></span> status rows |
                <span x-text="dashboard.agents.rows.length"></span> agent rows |
                <span x-text="dashboard.dispo.rows.length"></span> disposition rows
            </span>
        </summary>
        <div class="mt-4 space-y-4">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <div class="md-card p-4">
                    <p class="text-sm font-semibold text-[var(--color-on-surface)] mb-2">Call Status Raw</p>
                    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)] max-h-72 overflow-auto"
                         x-text="payloads.status?.data?.raw_response || payloads.status?.message || 'No call status data yet.'"></pre>
                </div>
                <div class="md-card p-4">
                    <p class="text-sm font-semibold text-[var(--color-on-surface)] mb-2">Agent Stats Raw</p>
                    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)] max-h-72 overflow-auto"
                         x-text="payloads.agents?.data?.raw_response || payloads.agents?.message || 'No agent data yet.'"></pre>
                </div>
                <div class="md-card p-4">
                    <p class="text-sm font-semibold text-[var(--color-on-surface)] mb-2">Disposition Raw</p>
                    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)] max-h-72 overflow-auto"
                         x-text="payloads.dispo?.data?.raw_response || payloads.dispo?.message || 'No disposition data yet.'"></pre>
                </div>
            </div>

            <div class="md-card p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-[var(--color-on-surface)]">Recording lookup utility</p>
                        <p class="text-xs text-[var(--color-on-surface-dim)]">Useful when a supervisor needs to confirm recording links or lead IDs.</p>
                    </div>
                    <button class="btn-secondary text-xs" @click="lookupRecordings(recordingFilters)">
                        Search recordings
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input class="form-input" type="text" placeholder="Agent user" x-model="recordingFilters.agent_user" />
                    <input class="form-input" type="number" placeholder="Lead ID" x-model="recordingFilters.lead_id" />
                    <input class="form-input" type="date" x-model="recordingFilters.date" />
                    <button class="btn-secondary" @click="lookupRecordings(recordingFilters)">Search</button>
                </div>
                <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)] max-h-72 overflow-auto"
                     x-text="payloads.recording?.data?.raw_response || payloads.recording?.message || 'No recording data yet.'"></pre>
            </div>
        </div>
    </details>
</div>
@endsection

@push('scripts')
<script>
window.telephonyReports = function () {
    const CHART_GROUP = 'telephony-reports';

    return {
        loading: false,
        errorMessage: '',
        filters: {
            campaigns: '---ALL---',
            query_date: new Date().toISOString().slice(0, 10),
            end_date: new Date().toISOString().slice(0, 10),
            disposition_scope: 'all',
        },
        dispositionScopeOptions: [
            { value: 'all', label: 'All dispositions' },
            { value: 'exclude_system', label: 'Hide system dispositions' },
            { value: 'system_only', label: 'System dispositions only' },
        ],
        systemDispositionCodes: @json(config('vicidial.report_system_disposition_codes', [])),
        recordingFilters: {
            agent_user: '',
            lead_id: '',
            date: new Date().toISOString().slice(0, 10),
        },
        payloads: {
            status: null,
            agents: null,
            dispo: null,
            recording: null,
        },
        dashboard: {
            scopeLabel: 'All dispositions',
            overview: {
                campaign: @json($campaignName),
                totalCalls: 0,
                answeredCalls: 0,
                answerRate: 0,
                topAgent: '—',
                topStatus: '—',
                topDisposition: '—',
                activeAgents: 0,
            },
            status: {
                rows: [],
                hourlyLabels: [],
                hourlyValues: [],
                statusLabels: [],
                statusValues: [],
            },
            agents: {
                rows: [],
                callsLabels: [],
                callsValues: [],
                summary: {
                    agentCount: 0,
                    totalCalls: 0,
                    totalTalkTime: '0:00:00',
                    totalPauseTime: '0:00:00',
                    avgTalkTime: '0:00:00',
                },
            },
            dispo: {
                rows: [],
                labels: [],
                values: [],
                summary: {
                    totalCalls: 0,
                    topDisposition: '—',
                    topDispositionCount: 0,
                },
            },
        },
        _onPopState: null,

        async init() {
            this._onPopState = () => this.refreshAll();
            window.addEventListener('popstate', this._onPopState);
            await this.refreshAll();
        },

        destroy() {
            if (this._onPopState) {
                window.removeEventListener('popstate', this._onPopState);
                this._onPopState = null;
            }
            this.destroyCharts();
        },

        destroyCharts() {
            window.crmCharts?.destroyGroup?.(CHART_GROUP);
        },

        async refreshAll() {
            this.loading = true;
            this.errorMessage = '';

            try {
                const [status, agents, dispo] = await Promise.all([
                    window.axios.get('/api/reports/call-status-stats', { params: this.filters }),
                    window.axios.get('/api/reports/agent-stats', { params: this.filters }),
                    window.axios.get('/api/reports/call-dispo-report', { params: this.filters }),
                ]);

                this.payloads.status = status.data;
                this.payloads.agents = agents.data;
                this.payloads.dispo = dispo.data;

                this.dashboard.status = this.normalizeCallStatus(status.data);
                this.dashboard.agents = this.normalizeAgentStats(agents.data);
                this.dashboard.dispo = this.normalizeDispositionReport(dispo.data, this.filters.disposition_scope);
                this.dashboard.scopeLabel = this.dashboard.dispo.summary.scopeLabel;

                this.dashboard.overview.totalCalls = this.dashboard.status.summary.totalCalls;
                this.dashboard.overview.answeredCalls = this.dashboard.status.summary.answeredCalls;
                this.dashboard.overview.answerRate = this.dashboard.status.summary.answerRate;
                this.dashboard.overview.topStatus = this.dashboard.status.summary.topStatus;
                this.dashboard.overview.topAgent = this.dashboard.agents.summary.topAgent;
                this.dashboard.overview.topDisposition = this.dashboard.dispo.summary.topDisposition;
                this.dashboard.overview.activeAgents = this.dashboard.agents.summary.agentCount;

                await this.renderCharts();
            } catch (e) {
                this.errorMessage = e.response?.data?.message || 'Failed to load report data.';
                Alpine.store('toast').error(this.errorMessage);
                this.destroyCharts();
            } finally {
                this.loading = false;
            }
        },

        async lookupRecordings(filters = {}) {
            try {
                const res = await window.axios.get('/api/call/recording/lookup', { params: filters });
                this.payloads.recording = res.data;
            } catch (e) {
                this.payloads.recording = e.response?.data || null;
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to lookup recordings.');
            }
        },

        normalizeCallStatus(response) {
            const rows = Array.isArray(response?.data?.rows) ? response.data.rows : [];
            const parsedRows = [];
            const hourlyTotals = Array.from({ length: 24 }, (_, hour) => ({ label: String(hour).padStart(2, '0'), value: 0 }));
            const statusTotals = new Map();

            rows.forEach((row, index) => {
                const values = Array.isArray(row) ? row : String(row || '').split('|');
                if (!values.length) {
                    return;
                }

                const label = String(values[0] || 'Unknown').trim();
                const total = this.toNumber(values[1]);
                const answered = this.toNumber(values[2]);
                const hourly = this.parseBreakdown(values[3], ',', '-');
                const statuses = this.parseBreakdown(values[4], ',', '-');
                const topStatus = [...statuses].sort((a, b) => b.value - a.value)[0] || null;
                const peakHour = [...hourly].sort((a, b) => b.value - a.value)[0] || null;

                hourly.forEach((entry) => {
                    const hourIndex = Number(entry.label);
                    if (Number.isInteger(hourIndex) && hourlyTotals[hourIndex]) {
                        hourlyTotals[hourIndex].value += entry.value;
                    }
                });

                statuses.forEach((entry) => {
                    const current = statusTotals.get(entry.label) || 0;
                    statusTotals.set(entry.label, current + entry.value);
                });

                parsedRows.push({
                    key: `${label}-${index}`,
                    label,
                    total,
                    answered,
                    answerRate: total > 0 ? (answered / total) * 100 : 0,
                    topStatus: topStatus?.label || '—',
                    peakHourLabel: peakHour?.label || '—',
                });
            });

            const totalCalls = parsedRows.reduce((sum, row) => sum + row.total, 0);
            const answeredCalls = parsedRows.reduce((sum, row) => sum + row.answered, 0);
            const topStatus = [...statusTotals.entries()].sort((a, b) => b[1] - a[1])[0];
            const topHour = [...hourlyTotals].sort((a, b) => b.value - a.value)[0];

            return {
                rows: parsedRows.sort((a, b) => b.total - a.total),
                hourlyLabels: hourlyTotals.map((entry) => entry.label),
                hourlyValues: hourlyTotals.map((entry) => entry.value),
                statusLabels: [...statusTotals.keys()],
                statusValues: [...statusTotals.values()],
                summary: {
                    totalCalls,
                    answeredCalls,
                    answerRate: totalCalls > 0 ? (answeredCalls / totalCalls) * 100 : 0,
                    topStatus: topStatus ? topStatus[0] : '—',
                    topHour: topHour ? topHour.label : '—',
                },
            };
        },

        normalizeAgentStats(response) {
            const rows = Array.isArray(response?.data?.rows) ? response.data.rows : [];
            if (!rows.length) {
                return {
                    rows: [],
                    callsLabels: [],
                    callsValues: [],
                    summary: {
                        agentCount: 0,
                        totalCalls: 0,
                        totalTalkTime: '0:00:00',
                        totalPauseTime: '0:00:00',
                        avgTalkTime: '0:00:00',
                        topAgent: '—',
                    },
                };
            }

            const headers = rows[0].map((header) => this.normalizeKey(header));
            const dataRows = rows.slice(1).map((row, index) => {
                const agent = {};

                headers.forEach((header, headerIndex) => {
                    agent[header] = row[headerIndex] ?? '';
                });

                return {
                    key: `${agent.user || 'agent'}-${index}`,
                    user: agent.user || '',
                    full_name: agent.full_name || agent.fullName || agent.user || 'Unknown',
                    user_group: agent.user_group || '',
                    calls: this.toNumber(agent.calls),
                    login_time: agent.login_time || '0:00:00',
                    total_talk_time: agent.total_talk_time || '0:00:00',
                    avg_talk_time: agent.avg_talk_time || '0:00:00',
                    avg_wait_time: agent.avg_wait_time || '0:00:00',
                    pause_time: agent.pause_time || '0:00:00',
                    pause_pct: agent.pause_pct || '0%',
                    total_wait_time: agent.total_wait_time || '0:00:00',
                };
            }).sort((a, b) => b.calls - a.calls);

            const totalCalls = dataRows.reduce((sum, row) => sum + row.calls, 0);
            const totalTalkSeconds = dataRows.reduce((sum, row) => sum + this.timeToSeconds(row.total_talk_time), 0);
            const totalPauseSeconds = dataRows.reduce((sum, row) => sum + this.timeToSeconds(row.pause_time), 0);
            const topAgent = dataRows[0] ? (dataRows[0].full_name || dataRows[0].user) : '—';

            return {
                rows: dataRows,
                callsLabels: dataRows.slice(0, 10).map((row) => row.full_name || row.user),
                callsValues: dataRows.slice(0, 10).map((row) => row.calls),
                summary: {
                    agentCount: dataRows.length,
                    totalCalls,
                    totalTalkTime: this.secondsToDuration(totalTalkSeconds),
                    totalPauseTime: this.secondsToDuration(totalPauseSeconds),
                    avgTalkTime: totalCalls > 0 ? this.secondsToDuration(totalTalkSeconds / totalCalls) : '0:00:00',
                    topAgent,
                },
            };
        },

        normalizeDispositionReport(response, scope = 'all') {
            const rows = Array.isArray(response?.data?.rows) ? response.data.rows : [];
            if (!rows.length) {
                return {
                    rows: [],
                    labels: [],
                    values: [],
                    summary: {
                        totalCalls: 0,
                        topDisposition: '—',
                        topDispositionCount: 0,
                        scopeLabel: this.dispositionScopeLabel(scope),
                    },
                };
            }

            let labels = rows[0].slice(2).map((header) => String(header || '').trim());
            const systemDispositionCodes = new Set(this.systemDispositionCodes
                .map((code) => this.normalizeDispositionCode(code))
                .filter(Boolean));
            const campaignRows = [];
            let totalRow = null;

            rows.slice(1).forEach((row, index) => {
                const values = Array.isArray(row) ? row : String(row || '').split(',');
                if (!values.length) {
                    return;
                }

                const campaign = String(values[0] || 'Unknown').trim();
                const metrics = labels.map((label, headerIndex) => {
                    const raw = values[headerIndex + 2] ?? '';
                    const parsed = this.parseNumericDisplay(raw);

                    return {
                        label,
                        value: parsed.value,
                        percent: parsed.percent,
                        system: systemDispositionCodes.has(this.normalizeDispositionCode(label)),
                    };
                });

                const scopedMetrics = metrics.filter((metric) => this.matchesDispositionScope(metric.system, scope));
                if (scope !== 'all' && scopedMetrics.length === 0) {
                    return;
                }

                const totalCalls = scopedMetrics.reduce((sum, metric) => sum + metric.value, 0);
                const topMetric = [...scopedMetrics].sort((a, b) => b.value - a.value)[0] || null;
                const breakdownSummary = scopedMetrics
                    .filter((metric) => metric.value > 0)
                    .slice(0, 3)
                    .map((metric) => `${metric.label}: ${this.formatNumber(metric.value)}`)
                    .join(' | ');

                const mappedRow = {
                    key: `${campaign}-${index}`,
                    label: campaign,
                    totalCalls,
                    topDisposition: topMetric?.label || '—',
                    topDispositionCount: topMetric?.value || 0,
                    breakdownSummary: breakdownSummary || 'No breakdown data',
                    metrics: scopedMetrics,
                    raw: values,
                };

                if (campaign.toUpperCase() === 'TOTAL') {
                    totalRow = mappedRow;
                } else {
                    campaignRows.push(mappedRow);
                }
            });

            const sourceRows = campaignRows.length > 0 ? campaignRows : totalRow ? [totalRow] : [];
            const labelTotals = new Map();
            sourceRows.forEach((row) => {
                row.metrics?.forEach((metric) => {
                    const current = labelTotals.get(metric.label) || 0;
                    labelTotals.set(metric.label, current + metric.value);
                });
            });

            labels = [...labelTotals.keys()];
            const values = [...labelTotals.values()];
            const topIndex = values.length > 0
                ? values.reduce((bestIndex, value, currentIndex, array) => {
                    if (value > array[bestIndex]) {
                        return currentIndex;
                    }

                    return bestIndex;
                }, 0)
                : -1;
            const totalCalls = values.reduce((sum, value) => sum + value, 0);

            return {
                rows: campaignRows.sort((a, b) => b.totalCalls - a.totalCalls),
                labels,
                values,
                summary: {
                    totalCalls,
                    topDisposition: labels[topIndex] || '—',
                    topDispositionCount: values[topIndex] || 0,
                    scopeLabel: this.dispositionScopeLabel(scope),
                },
            };
        },

        matchesDispositionScope(isSystemDisposition, scope) {
            if (scope === 'system_only') {
                return isSystemDisposition;
            }

            if (scope === 'exclude_system') {
                return ! isSystemDisposition;
            }

            return true;
        },

        normalizeDispositionCode(value) {
            return String(value ?? '').trim().toUpperCase();
        },

        dispositionScopeLabel(scope) {
            const option = this.dispositionScopeOptions.find((entry) => entry.value === scope);
            return option?.label || 'All dispositions';
        },

        async renderCharts() {
            this.destroyCharts();

            const ApexCharts = await window.ApexChartsLoader?.() ?? null;
            if (!ApexCharts) {
                return;
            }

            const statusHourlyEl = document.getElementById('chart-status-hourly');
            const statusMixEl = document.getElementById('chart-status-mix');
            const agentCallsEl = document.getElementById('chart-agent-calls');
            const dispoEl = document.getElementById('chart-dispo-breakdown');

            if (!statusHourlyEl && !statusMixEl && !agentCallsEl && !dispoEl) {
                return;
            }

            const chartTheme = window.crmChartTheme?.() ?? {};

            if (statusHourlyEl && this.dashboard.status.hourlyLabels.length) {
                const chart = new ApexCharts(statusHourlyEl, {
                    ...chartTheme,
                    series: [{ name: 'Calls', data: this.dashboard.status.hourlyValues }],
                    chart: { ...chartTheme.chart, type: 'area', height: 280 },
                    colors: [chartTheme.colors[0]],
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.32, opacityTo: 0.05 } },
                    stroke: { ...chartTheme.stroke, curve: 'smooth', width: 2 },
                    xaxis: { ...chartTheme.xaxis, categories: this.dashboard.status.hourlyLabels, labels: { ...chartTheme.xaxis.labels, style: { ...chartTheme.xaxis.labels.style, fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { ...chartTheme.yaxis, labels: { ...chartTheme.yaxis.labels, style: { ...chartTheme.yaxis.labels.style, fontSize: '11px' } }, min: 0 },
                    grid: { ...chartTheme.grid, strokeDashArray: 3 },
                    tooltip: { ...chartTheme.tooltip },
                    dataLabels: { enabled: false },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'status-hourly', chart);
                await chart.render();
            }

            if (statusMixEl && this.dashboard.status.statusLabels.length) {
                const chart = new ApexCharts(statusMixEl, {
                    ...chartTheme,
                    series: this.dashboard.status.statusValues,
                    chart: { ...chartTheme.chart, type: 'donut', height: 280 },
                    labels: this.dashboard.status.statusLabels,
                    colors: [chartTheme.colors[4], chartTheme.colors[1], chartTheme.colors[2], chartTheme.colors[3], chartTheme.colors[0], chartTheme.colors[5]],
                    dataLabels: { enabled: false },
                    legend: { ...chartTheme.legend, position: 'bottom' },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                            },
                        },
                    },
                    tooltip: { ...chartTheme.tooltip },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'status-mix', chart);
                await chart.render();
            }

            if (agentCallsEl && this.dashboard.agents.callsLabels.length) {
                const chart = new ApexCharts(agentCallsEl, {
                    ...chartTheme,
                    series: [{ name: 'Calls', data: this.dashboard.agents.callsValues }],
                    chart: { ...chartTheme.chart, type: 'bar', height: 280 },
                    colors: [chartTheme.colors[4]],
                    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%' } },
                    xaxis: { ...chartTheme.xaxis, categories: this.dashboard.agents.callsLabels, labels: { ...chartTheme.xaxis.labels, style: { ...chartTheme.xaxis.labels.style, fontSize: '11px' } } },
                    yaxis: { ...chartTheme.yaxis, labels: { ...chartTheme.yaxis.labels, style: { ...chartTheme.yaxis.labels.style }, maxWidth: 160 } },
                    grid: { ...chartTheme.grid, strokeDashArray: 3 },
                    tooltip: { ...chartTheme.tooltip },
                    dataLabels: { enabled: false },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'agent-calls', chart);
                await chart.render();
            }

            if (dispoEl && this.dashboard.dispo.labels.length) {
                const chart = new ApexCharts(dispoEl, {
                    ...chartTheme,
                    series: this.dashboard.dispo.values,
                    chart: { ...chartTheme.chart, type: 'donut', height: 280 },
                    labels: this.dashboard.dispo.labels,
                    colors: [chartTheme.colors[1], chartTheme.colors[4], chartTheme.colors[2], chartTheme.colors[3], chartTheme.colors[0], chartTheme.colors[5], chartTheme.colors[2], chartTheme.colors[4]],
                    dataLabels: { enabled: false },
                    legend: { ...chartTheme.legend, position: 'bottom' },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                            },
                        },
                    },
                    tooltip: { ...chartTheme.tooltip },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'dispo-breakdown', chart);
                await chart.render();
            }

            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            window.crmCharts?.resizeGroup?.(CHART_GROUP);
        },

        parseBreakdown(value, entrySeparator = ',', pairSeparator = '-') {
            return String(value || '')
                .split(entrySeparator)
                .map((entry) => entry.trim())
                .filter(Boolean)
                .map((entry) => {
                    const [label = '', amount = ''] = entry.split(pairSeparator);

                    return {
                        label: label.trim(),
                        value: this.toNumber(amount),
                    };
                });
        },

        parseNumericDisplay(value) {
            const text = String(value ?? '').trim();
            const match = text.match(/^(-?[\d.]+)\s*\(([-\d.]+)%\)$/);

            if (match) {
                return {
                    value: Number(match[1]) || 0,
                    percent: Number(match[2]) || 0,
                };
            }

            return {
                value: this.toNumber(text),
                percent: this.toPercent(text),
            };
        },

        normalizeKey(value) {
            return String(value || '')
                .trim()
                .replace(/\s+/g, '_')
                .toLowerCase();
        },

        toNumber(value) {
            const parsed = Number(String(value ?? '').replace(/[^0-9.-]/g, ''));
            return Number.isFinite(parsed) ? parsed : 0;
        },

        toPercent(value) {
            const text = String(value ?? '').trim();
            if (!text) {
                return 0;
            }

            const match = text.match(/(-?[\d.]+)%/);
            if (match) {
                return Number(match[1]) || 0;
            }

            return this.toNumber(text);
        },

        timeToSeconds(value) {
            const text = String(value ?? '').trim();
            if (!text || text === '0') {
                return 0;
            }

            const parts = text.split(':').map((segment) => Number(segment));
            if (parts.some((segment) => Number.isNaN(segment))) {
                return this.toNumber(text);
            }

            if (parts.length === 3) {
                return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
            }

            if (parts.length === 2) {
                return (parts[0] * 60) + parts[1];
            }

            return parts[0] || 0;
        },

        secondsToDuration(seconds) {
            const total = Math.max(0, Math.round(Number(seconds) || 0));
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const secs = total % 60;

            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },

        formatNumber(value) {
            return new Intl.NumberFormat().format(Number(value) || 0);
        },

        formatPercent(value) {
            return `${(Number(value) || 0).toFixed(1)}%`;
        },
    };
};
</script>
@endpush
