@extends('layouts.app')

@section('title', 'Telephony Reports')
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Telephony Reports')

@section('content')
<div x-data="telephonyReports()" x-init="init()" class="reports-page space-y-6">
    <x-page-header
        title="Telephony Reports"
        description="Historical performance, trends, and management reporting."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => null]" />

    <template x-if="errorMessage">
        <div class="report-notice report-notice--danger" role="alert" aria-live="assertive">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[var(--color-danger)]" x-text="mode === 'historical' ? 'Historical report data could not be loaded' : 'Live report data could not be loaded'"></p>
                    <p class="text-xs text-[var(--color-on-surface-muted)] mt-1" x-text="errorMessage"></p>
                </div>
                <button type="button" class="btn-secondary text-xs" @click="refreshAll()">Retry</button>
            </div>
        </div>
    </template>

    <div x-show="dashboard.availability.status && dashboard.availability.status !== 'live'"
         class="report-notice report-notice--warning"
         role="status"
         aria-live="polite">
        <p class="text-sm font-semibold text-[var(--color-warning)]"
           x-text="dashboard.availability.status === 'stale' ? 'Showing the last successful report snapshot' : (dashboard.availability.status === 'unavailable' ? (mode === 'historical' ? 'Historical reports unavailable' : 'Live report sources unavailable') : 'Report sources are partially available')"></p>
        <p class="text-xs text-[var(--color-on-surface-muted)] mt-1"
           x-text="dashboard.availability.message || 'Some report sections could not be loaded.'"></p>
    </div>

    <section class="reports-command-center" x-data="{ advancedFiltersOpen: false }" aria-label="Report controls and scope">
        <div class="reports-command-center__main">
            <div class="min-w-0">
                <p class="report-eyebrow">Reporting Workspace</p>
                <h2 class="reports-command-center__title" x-text="mode === 'historical' ? 'Historical Performance' : (mode === 'today' ? 'Today at a Glance' : 'Live Reporting')"></h2>
                <div class="reports-context-strip">
                    <span class="reports-context-item">
                        <x-icon name="building-office" class="h-4 w-4" />
                        <span class="reports-context-label">Campaign</span>
                        <span x-text="dashboard.overview.campaign"></span>
                    </span>
                    <span class="reports-context-item" x-show="mode === 'historical'">
                        <x-icon name="clock" class="h-4 w-4" />
                        <span class="reports-context-label">Period</span>
                        <span x-text="filters.query_date + ' to ' + filters.end_date"></span>
                    </span>
                    <span class="reports-context-item" x-show="mode !== 'historical'">
                        <x-icon name="clock" class="h-4 w-4" />
                        <span class="reports-context-label">Window</span>
                        <span x-text="mode === 'today' ? 'Midnight to now' : cleanReportText(realtime.scopeLabel || 'Rolling Operational Window')"></span>
                    </span>
                    <span class="badge"
                          :class="mode === 'historical' ? 'badge-pending' : (realtime.status === 'live' ? 'badge-active' : 'badge-warning')"
                          x-text="mode === 'historical' ? 'Historical' : humanizeLabel(realtime.status || 'Unavailable')"></span>
                </div>
            </div>

            <div class="reports-command-center__actions">
                <div class="reports-mode-switcher">
                    <label for="reports-mode">Report Mode</label>
                    <select id="reports-mode" class="form-select" x-model="mode" @change="changeMode()">
                        <option value="live">Live: Rolling Window</option>
                        <option value="today">Today: Midnight to Now</option>
                        <option value="historical">Historical: Custom Range</option>
                    </select>
                </div>
                <button class="btn-primary reports-refresh-button" @click="refreshAll()" x-bind:disabled="loading">
                    <span class="inline-flex" x-bind:class="loading ? 'animate-spin' : ''">
                        <x-icon name="arrow-path" class="w-4 h-4" />
                    </span>
                    <span x-text="loading ? 'Loading...' : 'Refresh Reports'">Refresh Reports</span>
                </button>
            </div>
        </div>

        <div x-show="mode === 'historical'" x-cloak class="reports-filter-bar">
            <div class="reports-filter-grid">
                <div class="form-field">
                    <label class="form-label" for="reports-crm-campaign">CRM Campaign</label>
                    <select id="reports-crm-campaign" class="form-select" x-model="filters.crm_campaign" @change="filters.campaigns = '---ALL---'; refreshAll()">
                        @foreach($reportCampaigns ?? [] as $code => $config)
                            <option value="{{ $code }}">{{ $config['name'] ?? $code }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="reports-date-start">Start Date</label>
                    <input id="reports-date-start" class="form-input" type="date" x-model="filters.query_date" />
                </div>
                <div class="form-field">
                    <label class="form-label" for="reports-date-end">End Date</label>
                    <input id="reports-date-end" class="form-input" type="date" x-model="filters.end_date" />
                </div>
                <div class="reports-filter-actions">
                    <button type="button"
                            class="btn-secondary"
                            @click="advancedFiltersOpen = !advancedFiltersOpen"
                            :aria-expanded="advancedFiltersOpen"
                            aria-controls="reports-advanced-filters">
                        <x-icon name="adjustments-horizontal" class="w-4 h-4" />
                        <span x-text="advancedFiltersOpen ? 'Fewer Filters' : 'More Filters'">More Filters</span>
                    </button>
                </div>
            </div>

            <div id="reports-advanced-filters"
                 class="reports-advanced-filters"
                 x-show="advancedFiltersOpen"
                 x-cloak>
                <div class="form-field">
                    <label class="form-label" for="reports-vici-campaign">VICIdial Campaigns</label>
                    <select id="reports-vici-campaign" class="form-select" x-model="filters.campaigns">
                        <option value="---ALL---">All Mapped Campaigns</option>
                        <template x-for="code in mappedCampaigns" :key="code">
                            <option :value="code" x-text="code"></option>
                        </template>
                    </select>
                    <p class="form-help">Only campaigns mapped to the selected CRM campaign are available.</p>
                </div>
                <div class="form-field">
                    <label class="form-label" for="reports-disposition-scope">Disposition Scope</label>
                    <select id="reports-disposition-scope" class="form-select" x-model="filters.disposition_scope" @change="refreshAll()">
                        <template x-for="option in dispositionScopeOptions" :key="option.value">
                            <option :value="option.value" x-text="option.label"></option>
                        </template>
                    </select>
                    <p class="form-help">
                        System codes:
                        <span class="font-medium text-[var(--color-on-surface-muted)]" x-text="systemDispositionCodes.length ? systemDispositionCodes.join(', ') : 'None configured'"></span>
                    </p>
                </div>
                <div class="form-field">
                    <label class="form-label" for="reports-comparison">Comparison</label>
                    <select id="reports-comparison" class="form-select" x-model="filters.comparison">
                        <option value="none">No Comparison</option>
                        <option value="previous_period">Previous Period</option>
                        <option value="previous_day">Previous Day</option>
                        <option value="previous_week">Previous Week</option>
                        <option value="previous_month">Previous Month</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section x-show="mode !== 'historical'" x-cloak class="report-section-shell" aria-labelledby="live-report-title">
        <div class="report-section-header">
            <div>
                <h3 id="live-report-title" class="report-section-title" x-text="mode === 'today' ? 'Today and Live Operations' : 'Live Operational Metrics'"></h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="realtime.scopeLabel"></p>
            </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" role="status" aria-live="polite">
                <span x-text="realtime.lastUpdated ? 'Updated ' + realtime.lastUpdated : 'Waiting for first refresh'">Waiting for first refresh</span>
            </p>
        </div>

        <div class="reports-live-kpis">
            <template x-for="card in realtime.cards" :key="card.key">
                <div class="reports-live-kpi">
                    <p class="report-metric-label" x-text="card.label"></p>
                    <p class="reports-live-kpi__value" x-text="card.value"></p>
                    <p class="mt-1 text-[11px] text-[var(--color-on-surface-dim)]" x-text="card.scope"></p>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container report-panel report-chart-panel">
                <p class="chart-title">Live Call Activity</p>
                <div id="chart-live-activity" aria-label="Live call activity chart">
                    <div x-show="!liveHistory.length" x-cloak class="crm-empty-state report-empty-state" role="status" aria-live="polite">
                        <span class="crm-empty-state-icon" aria-hidden="true"><x-icon name="signal" class="w-5 h-5" /></span>
                        <div class="min-w-0">
                            <p class="crm-empty-state-title">Waiting for a live snapshot</p>
                            <p class="crm-empty-state-description">Live activity appears after the first polling response is received.</p>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-2">The chart contains only snapshots received while this page is open.</p>
            </div>
            <div class="report-panel report-source-health">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h4 class="report-section-title">Source Health</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)]">Color is supplementary; the status text is authoritative.</p>
                    </div>
                    <span class="badge" :class="realtime.status === 'live' ? 'badge-active' : (realtime.status === 'degraded' ? 'badge-warning' : 'badge-error')" x-text="humanizeLabel(realtime.status)"></span>
                </div>
                <ul class="space-y-2 text-sm">
                    <template x-for="source in realtime.sources" :key="source.key">
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-[var(--color-border)] px-3 py-2">
                            <span class="text-[var(--color-on-surface)]" x-text="humanizeLabel(source.label)"></span>
                            <span class="text-xs font-medium" :class="source.status === 'healthy' ? 'text-[var(--color-success)]' : 'text-[var(--color-warning)]'" x-text="humanizeLabel(source.status)"></span>
                        </li>
                    </template>
                </ul>
                <p class="text-xs text-[var(--color-on-surface-dim)]" x-show="realtime.staleMessage" x-text="realtime.staleMessage"></p>
            </div>
        </div>

        <div class="report-panel report-outcomes-panel">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h4 class="report-section-title">Recent Completed Outcomes</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="realtime.rollingScope"></p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="realtime.dispositions.length + ' dispositions'"></span>
            </div>
            <div class="flex flex-wrap gap-2" x-show="realtime.dispositions.length">
                <template x-for="item in realtime.dispositions" :key="item.code">
                    <span class="badge badge-pending"><span x-text="item.code"></span>: <span x-text="item.count"></span></span>
                </template>
            </div>
            <p class="text-sm text-[var(--color-on-surface-dim)]" x-show="!realtime.dispositions.length">No completed dispositions were returned for this window.</p>
        </div>
    </section>

    <div x-show="mode === 'historical'" x-cloak class="reports-historical-stack">

    <section class="md-card overflow-hidden animate-stagger" aria-labelledby="report-performance-title">
        <div class="h-1 bg-[var(--color-primary)]" aria-hidden="true"></div>

        <div class="px-5 py-5 sm:px-6 sm:py-6">
            <div class="flex flex-col gap-4 border-b border-[var(--color-border)] pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[var(--color-primary)]">
                        <x-icon name="chart-bar" class="h-4 w-4 shrink-0" />
                        <p class="report-eyebrow">Performance Snapshot</p>
                    </div>
                    <h3 id="report-performance-title" class="mt-2 truncate text-xl font-bold text-[var(--color-on-surface)]" x-text="dashboard.overview.campaign"></h3>
                    <p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">Core call performance for the selected report scope.</p>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-[var(--color-on-surface-dim)]">
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="clock" class="h-4 w-4 text-[var(--color-on-surface-muted)]" />
                        <span x-text="filters.query_date + ' to ' + filters.end_date"></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="tag" class="h-4 w-4 text-[var(--color-on-surface-muted)]" />
                        <span x-text="humanizeLabel(dashboard.scopeLabel)"></span>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 pt-5 xl:grid-cols-[minmax(16rem,0.9fr)_minmax(0,2.1fr)] xl:gap-6">
                <div class="relative overflow-hidden rounded-xl border border-[color-mix(in_srgb,var(--color-primary)_35%,var(--color-border))] bg-[var(--color-primary-muted)] p-5 sm:p-6">
                    <div class="absolute inset-y-0 left-0 w-1 bg-[var(--color-primary)]" aria-hidden="true"></div>
                    <p class="report-eyebrow">Answer Rate</p>
                    <p class="mt-3 text-4xl font-extrabold leading-none tracking-tight text-[var(--color-on-surface)] tabular-nums sm:text-5xl" x-text="formatPercent(dashboard.overview.answerRate)"></p>
                    <p class="mt-3 text-sm leading-6 text-[var(--color-on-surface-muted)]">
                        <span class="font-semibold text-[var(--color-on-surface)] tabular-nums" x-text="formatNumber(dashboard.overview.answeredCalls)"></span>
                        answered from
                        <span class="font-semibold text-[var(--color-on-surface)] tabular-nums" x-text="formatNumber(dashboard.overview.totalCalls)"></span>
                        total calls.
                    </p>
                </div>

                <dl class="grid grid-cols-2 overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] sm:grid-cols-3">
                    <div class="min-w-0 border-b border-r border-[var(--color-border)] p-4 sm:p-5">
                        <dt class="report-metric-label">Total Calls</dt>
                        <dd class="mt-2 text-2xl font-bold text-[var(--color-on-surface)] tabular-nums" x-text="formatNumber(dashboard.overview.totalCalls)"></dd>
                    </div>
                    <div class="min-w-0 border-b border-[var(--color-border)] p-4 sm:border-r sm:p-5">
                        <dt class="report-metric-label">Answered</dt>
                        <dd class="mt-2 text-2xl font-bold text-[var(--color-success)] tabular-nums" x-text="formatNumber(dashboard.overview.answeredCalls)"></dd>
                    </div>
                    <div class="min-w-0 border-b border-r border-[var(--color-border)] p-4 sm:border-r-0 sm:p-5">
                        <dt class="report-metric-label">Contact Rate</dt>
                        <dd class="mt-2 text-2xl font-bold text-[var(--color-on-surface)] tabular-nums" x-text="formatPercent(dashboard.overview.contactRate)"></dd>
                    </div>
                    <div class="min-w-0 border-b border-[var(--color-border)] p-4 sm:border-b-0 sm:border-r sm:p-5">
                        <dt class="report-metric-label">Calls per Agent</dt>
                        <dd class="mt-2 text-2xl font-bold text-[var(--color-on-surface)] tabular-nums" x-text="formatNumber(dashboard.overview.callsPerAgent)"></dd>
                    </div>
                    <div class="min-w-0 border-r border-[var(--color-border)] p-4 sm:p-5">
                        <dt class="report-metric-label">Average Talk Time</dt>
                        <dd class="mt-2 truncate text-xl font-bold text-[var(--color-on-surface)] tabular-nums" x-text="formatDuration(dashboard.overview.averageTalkTimeSeconds)"></dd>
                    </div>
                    <div class="min-w-0 p-4 sm:p-5">
                        <dt class="report-metric-label">Agents With Activity</dt>
                        <dd class="mt-2 text-2xl font-bold text-[var(--color-on-surface)] tabular-nums" x-text="formatNumber(dashboard.overview.agentsWithActivity)"></dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <section x-show="dashboard.comparison.enabled" class="report-panel report-comparison-panel" aria-labelledby="comparison-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="comparison-title" class="text-sm font-semibold text-[var(--color-on-surface)]">Period Comparison</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">
                    Comparing with
                    <span class="font-medium" x-text="dashboard.comparison.periodLabel"></span>
                </p>
            </div>
            <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.comparison.availabilityLabel"></span>
        </div>
        <div class="report-comparison-grid">
            <template x-for="item in dashboard.comparison.cards" :key="item.key">
                <div class="report-comparison-metric">
                    <p class="report-metric-label" x-text="item.label"></p>
                    <p class="mt-1 text-lg font-semibold text-[var(--color-on-surface)]" x-text="item.value"></p>
                    <p class="mt-1 text-xs" :class="item.tone" x-text="item.changeLabel"></p>
                </div>
            </template>
        </div>
    </section>

    <section class="report-section-shell">
        <div class="report-section-header">
            <div>
                <h3 class="report-section-title">Call Volume Trend</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">Historical activity for the selected campaign and date range.</p>
            </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="'Rows loaded: ' + dashboard.status.rows.length"></p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container report-panel report-chart-panel">
                <p class="chart-title">Hourly Volume</p>
                <div x-show="dashboard.status.hourlyLabels.length" x-cloak id="chart-status-hourly" class="w-full" style="min-height: 280px;"></div>
                <div x-show="!dashboard.status.hourlyLabels.length" x-cloak class="crm-empty-state report-empty-state" role="status" aria-live="polite">
                    <span class="crm-empty-state-icon" aria-hidden="true"><x-icon name="chart-bar" class="w-5 h-5" /></span>
                    <div class="min-w-0">
                        <p class="crm-empty-state-title" x-text="reportSectionTitle(dashboard.status.hourlyState, 'Hourly Volume')">Hourly Volume Unavailable</p>
                        <p class="crm-empty-state-description" x-text="reportSectionDescription(dashboard.status.hourlyState, 'Hourly Volume')">Hourly volume is unavailable for this scope.</p>
                    </div>
                </div>
            </div>
            <div class="chart-container report-panel report-chart-panel">
                <p class="chart-title">Status Mix</p>
                <div x-show="dashboard.status.statusLabels.length" x-cloak id="chart-status-mix" class="w-full" style="min-height: 280px;"></div>
                <div x-show="!dashboard.status.statusLabels.length" x-cloak class="crm-empty-state report-empty-state" role="status" aria-live="polite">
                    <span class="crm-empty-state-icon" aria-hidden="true"><x-icon name="chart-pie" class="w-5 h-5" /></span>
                    <div class="min-w-0">
                        <p class="crm-empty-state-title" x-text="reportSectionTitle(dashboard.status.statusState, 'Status Breakdown')">Status Breakdown Unavailable</p>
                        <p class="crm-empty-state-description" x-text="reportSectionDescription(dashboard.status.statusState, 'Status Breakdown')">Status breakdown is unavailable for this scope.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-table-panel">
            <div class="px-5 py-4 border-b border-[var(--color-border)] flex items-center justify-between gap-3">
                <div>
                    <h4 class="report-section-title">Call Status Breakdown</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Each row is grouped by campaign or ingroup.</p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.status.rows.length + (dashboard.status.rows.length === 1 ? ' row' : ' rows')"></span>
            </div>
            <div class="md-table-wrap">
                <x-table.index caption="Call Status Breakdown Table">
                    <x-table.head :columns="[
                        ['label' => 'Campaign and In-Group'],
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
                        <tr x-show="!dashboard.status.rows.length">
                            <td colspan="6" class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]" x-text="reportSectionMessage(dashboard.status.statusState, 'Call status data')"></td>
                        </tr>
                    </tbody>
                </x-table.index>
            </div>
        </div>
    </section>

    <section class="report-section-shell">
        <div class="report-section-header">
            <div>
                <h3 class="report-section-title">Agent Performance</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">Readable agent activity summaries built from VICIdial export rows.</p>
            </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="'Agents loaded: ' + dashboard.agents.rows.length"></p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container report-panel report-chart-panel">
                <p class="chart-title">Calls by Agent</p>
                <div x-show="dashboard.agents.callsLabels.length" id="chart-agent-calls" class="w-full" style="min-height: 280px;"></div>
                <div x-show="!dashboard.agents.callsLabels.length" class="table-empty py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                    No agent rows yet.
                </div>
            </div>
            <div class="chart-container report-panel report-chart-panel">
                <p class="chart-title">Talk Time Balance</p>
                <div class="report-inline-metrics">
                    <div class="report-inline-metric">
                        <p class="report-metric-label">Total Talk</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)]" x-text="dashboard.agents.summary.totalTalkTime"></p>
                    </div>
                    <div class="report-inline-metric">
                        <p class="report-metric-label">Average Talk Time per Call</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)]" x-text="dashboard.agents.summary.avgTalkTime"></p>
                    </div>
                    <div class="report-inline-metric">
                        <p class="report-metric-label">Total Pause</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)]" x-text="dashboard.agents.summary.totalPauseTime"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-table-panel">
            <div class="px-5 py-4 border-b border-[var(--color-border)] flex items-center justify-between gap-3">
                <div>
                    <h4 class="report-section-title">Agent Performance Table</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Sorted by calls in the selected range.</p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.agents.rows.length + (dashboard.agents.rows.length === 1 ? ' agent' : ' agents')"></span>
            </div>
            <div class="md-table-wrap">
                <x-table.index caption="Agent Performance Table">
                    <x-table.head :columns="[
                        ['label' => 'Agent'],
                        ['label' => 'Group'],
                        ['label' => 'Calls', 'align' => 'right'],
                        ['label' => 'Talk Time', 'align' => 'right'],
                        ['label' => 'Average Talk Time', 'align' => 'right'],
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
                        <tr x-show="!dashboard.agents.rows.length">
                            <td colspan="7" class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No agent rows were returned for this scope.</td>
                        </tr>
                    </tbody>
                </x-table.index>
            </div>
        </div>
    </section>

    <section class="report-section-shell">
        <div class="report-section-header">
                <div>
                    <h3 class="report-section-title">Disposition Pareto</h3>
                    <p class="text-xs text-[var(--color-on-surface-dim)]">
                        Disposition totals and percentages for the selected report window.
                        <span class="font-medium text-[var(--color-on-surface-muted)]" x-text="'Scope: ' + humanizeLabel(dashboard.scopeLabel)"></span>
                    </p>
                </div>
            <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="'Disposition rows: ' + dashboard.dispo.rows.length"></p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="chart-container report-panel report-chart-panel">
                <p class="chart-title">Top Dispositions by Volume</p>
                <div x-show="dashboard.dispo.labels.length" x-cloak id="chart-dispo-breakdown" class="w-full" style="min-height: 280px;"></div>
                <div x-show="!dashboard.dispo.labels.length" x-cloak class="crm-empty-state report-empty-state" role="status" aria-live="polite">
                    <span class="crm-empty-state-icon" aria-hidden="true"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="min-w-0">
                        <p class="crm-empty-state-title" x-text="reportSectionTitle(dashboard.dispo.state, 'Disposition Data')">Disposition Data Unavailable</p>
                        <p class="crm-empty-state-description" x-text="reportSectionDescription(dashboard.dispo.state, 'Disposition Data')">Disposition data is unavailable for this scope.</p>
                    </div>
                </div>
            </div>
            <div class="report-panel report-totals-panel">
                <p class="chart-title mb-0">Report Totals</p>
                <div class="report-inline-metrics report-inline-metrics--two">
                    <div class="report-inline-metric">
                        <p class="report-metric-label">Total Calls</p>
                        <p class="mt-2 text-2xl font-bold text-[var(--color-on-surface)]" x-text="formatNumber(dashboard.dispo.summary.totalCalls)"></p>
                    </div>
                    <div class="report-inline-metric">
                        <p class="report-metric-label">Contact Rate</p>
                        <p class="mt-2 text-lg font-semibold text-[var(--color-on-surface)] truncate" x-text="formatPercent(dashboard.overview.contactRate)"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-table-panel">
            <div class="px-5 py-4 border-b border-[var(--color-border)] flex items-center justify-between gap-3">
                <div>
                    <h4 class="report-section-title">Disposition Table</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Each row mirrors the VICIdial export but with the total row kept in the debug area.</p>
                </div>
                <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard.dispo.rows.length + (dashboard.dispo.rows.length === 1 ? ' campaign' : ' campaigns')"></span>
            </div>
            <div class="md-table-wrap">
                <x-table.index caption="Disposition Table">
                    <x-table.head :columns="[
                        ['label' => 'Campaign and In-Group'],
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
                        <tr x-show="!dashboard.dispo.rows.length">
                            <td colspan="4" class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]" x-text="reportSectionMessage(dashboard.dispo.state, 'Disposition data')"></td>
                        </tr>
                    </tbody>
                </x-table.index>
            </div>
        </div>
    </section>

    <section class="report-section-shell grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="report-panel" x-show="dashboard.funnel.length >= 2">
            <div>
                <h3 class="report-section-title">Call Funnel</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">Only configured disposition stages are shown.</p>
            </div>
            <ol class="space-y-2">
                <template x-for="stage in dashboard.funnel" :key="stage.key">
                    <li class="flex items-center justify-between gap-3 rounded-lg border border-[var(--color-border)] px-3 py-2">
                        <span class="text-sm text-[var(--color-on-surface)]" x-text="stage.label"></span>
                        <span class="font-semibold tabular-nums text-[var(--color-on-surface)]" x-text="formatNumber(stage.value)"></span>
                    </li>
                </template>
            </ol>
        </div>
        <div class="report-panel">
            <div>
                <h3 class="report-section-title">Agent Time Distribution</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)]">Only durations supplied by VICIdial are shown.</p>
            </div>
            <div class="report-inline-metrics report-inline-metrics--two">
                <template x-for="item in dashboard.timeDistribution" :key="item.key">
                    <div class="report-inline-metric">
                        <p class="report-metric-label" x-text="item.label"></p>
                        <p class="mt-1 text-lg font-semibold text-[var(--color-on-surface)]" x-text="formatDuration(item.seconds)"></p>
                    </div>
                </template>
            </div>
        </div>
    </section>

    </div>

    @if(auth()->user()?->isAdmin())
    <details class="report-debug-panel">
        <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
            <div>
                <h3 class="report-section-title">Debug and Raw VICIdial Output</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Collapsed by default. Open this only when the dashboard needs diagnosis.</p>
            </div>
            <span class="text-xs text-[var(--color-on-surface-dim)]">
                <span x-text="dashboard.status.rows.length"></span> <span x-text="dashboard.status.rows.length === 1 ? 'status row' : 'status rows'"></span>,
                <span x-text="dashboard.agents.rows.length"></span> <span x-text="dashboard.agents.rows.length === 1 ? 'agent row' : 'agent rows'"></span>,
                <span x-text="dashboard.dispo.rows.length"></span> <span x-text="dashboard.dispo.rows.length === 1 ? 'disposition row' : 'disposition rows'"></span>
            </span>
        </summary>
        <div class="mt-4 space-y-4">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <div class="md-card p-4">
                    <p class="text-sm font-semibold text-[var(--color-on-surface)] mb-2">Source availability</p>
                    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)] max-h-72 overflow-auto"
                         x-text="JSON.stringify(dashboard.availability || {}, null, 2)"></pre>
                </div>
                <div class="md-card p-4 xl:col-span-2">
                    <p class="text-sm font-semibold text-[var(--color-on-surface)] mb-2">Normalized dashboard response</p>
                    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)] max-h-72 overflow-auto"
                         x-text="JSON.stringify(payloads.dashboard?.data || {}, null, 2)"></pre>
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
    @endif
</div>
@endsection

@push('scripts')
<script>
window.REPORT_LIVE_POLL_SECONDS = Math.max(5, @json((int) config('vicidial.supervisor.poll_seconds', 15)));
window.telephonyReports = function () {
    const CHART_GROUP = 'telephony-reports';

    return {
        mode: 'historical',
        loading: false,
        errorMessage: '',
        refreshInFlight: false,
        pollInterval: null,
        requestController: null,
        liveHistory: [],
        liveChart: null,
        hasDashboardSnapshot: false,
        dashboardSnapshotKey: '',
        hasRealtimeSnapshot: false,
        realtimeSnapshotKey: '',
        filters: {
            crm_campaign: @json($campaign),
            campaigns: '---ALL---',
            query_date: new Date().toISOString().slice(0, 10),
            end_date: new Date().toISOString().slice(0, 10),
            timezone: @json(config('vicidial.report_timezone', config('app.timezone', 'UTC'))),
            disposition_scope: 'all',
            comparison: 'none',
        },
        mappedCampaigns: @json($vicidialCampaignCodes ?? []),
        dispositionScopeOptions: [
            { value: 'all', label: 'All Dispositions' },
            { value: 'exclude_system', label: 'Hide System Dispositions' },
            { value: 'system_only', label: 'System Dispositions Only' },
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
            scopeLabel: 'All Dispositions',
            overview: {
                campaign: @json($campaignName),
                totalCalls: null,
                answeredCalls: null,
                answerRate: null,
                contactRate: null,
                averageTalkTimeSeconds: null,
                agentsWithActivity: null,
                callsPerAgent: null,
                topAgent: 'N/A',
                topStatus: 'N/A',
                topDisposition: 'N/A',
                activeAgents: 0,
            },
            availability: {
                status: 'unavailable',
                message: '',
            },
            comparison: {
                enabled: false,
                periodLabel: '',
                availabilityLabel: '',
                cards: [],
            },
            status: {
                rows: [],
                hourlyLabels: [],
                hourlyValues: [],
                hourlyState: 'loading',
                statusLabels: [],
                statusValues: [],
                statusState: 'loading',
            },
            agents: {
                rows: [],
                callsLabels: [],
                callsValues: [],
                summary: {
                    agentCount: 0,
                    totalCalls: null,
                    totalTalkTime: 'N/A',
                    totalPauseTime: 'N/A',
                    avgTalkTime: 'N/A',
                },
            },
            dispo: {
                rows: [],
                labels: [],
                values: [],
                state: 'loading',
                summary: {
                    totalCalls: null,
                    topDisposition: 'N/A',
                    topDispositionCount: null,
                },
            },
            funnel: [],
            timeDistribution: [
                { key: 'talk', label: 'Talk', seconds: null },
                { key: 'pause', label: 'Pause', seconds: null },
                { key: 'ready', label: 'Ready', seconds: null },
                { key: 'other', label: 'Other', seconds: null },
            ],
        },
        realtime: {
            mode: '',
            status: 'unavailable',
            scopeLabel: 'Rolling operational window',
            rollingScope: 'Rolling metrics unavailable',
            lastUpdated: '',
            staleMessage: '',
            cards: [],
            sources: [],
            dispositions: [],
        },
        _onPopState: null,
        _onVisibilityChange: null,

        async init() {
            this._onPopState = () => this.refreshAll();
            window.addEventListener('popstate', this._onPopState);
            this._onVisibilityChange = () => {
                if (document.hidden) {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                    }
                    return;
                }

                this.startPolling();
            };
            document.addEventListener('visibilitychange', this._onVisibilityChange);
            await this.refreshAll();
            this.startPolling();
        },

        destroy() {
            if (this._onPopState) {
                window.removeEventListener('popstate', this._onPopState);
                this._onPopState = null;
            }
            if (this._onVisibilityChange) {
                document.removeEventListener('visibilitychange', this._onVisibilityChange);
                this._onVisibilityChange = null;
            }
            if (this.requestController) {
                this.requestController.abort();
                this.requestController = null;
            }
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            this.destroyCharts();
        },

        destroyCharts() {
            window.crmCharts?.destroyGroup?.(CHART_GROUP);
            this.liveChart = null;
        },

        async refreshAll() {
            if (this.refreshInFlight) {
                return;
            }
            this.loading = true;
            this.errorMessage = '';
            this.refreshInFlight = true;
            this.requestController = new AbortController();

            try {
                const endpoint = this.mode === 'historical'
                    ? '/api/reports/dashboard'
                    : `/api/reports/realtime/${this.mode}`;
                const response = await window.axios.get(endpoint, {
                    params: {
                        ...this.filters,
                        campaign: this.filters.crm_campaign,
                    },
                    signal: this.requestController.signal,
                });
                const data = response.data?.data ?? {};
                this.payloads.dashboard = response.data;
                if (this.mode === 'historical') {
                    this.applyDashboard(data);
                } else {
                    this.applyRealtime(data);
                }
                await this.renderCharts();
            } catch (e) {
                if (e.code === 'ERR_CANCELED' || e.name === 'CanceledError' || e.name === 'AbortError') {
                    return;
                }
                this.errorMessage = e.response?.data?.message || 'Failed to load report data.';
                if (this.mode === 'historical' && this.hasDashboardSnapshot) {
                    this.dashboard.availability = {
                        ...this.dashboard.availability,
                        status: 'stale',
                        message: 'The last successful report snapshot is being shown. Retry to request fresh data.',
                    };
                }
                if (this.mode !== 'historical' && this.realtime.lastUpdated) {
                    this.realtime.status = 'stale';
                    this.realtime.staleMessage = 'The last live snapshot could not be refreshed. Retry to request a fresh snapshot.';
                }
                Alpine.store('toast').error(this.errorMessage);
            } finally {
                this.loading = false;
                this.refreshInFlight = false;
                this.requestController = null;
            }
        },

        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            if (this.mode === 'historical') {
                return;
            }
            if (document.hidden) {
                return;
            }
            this.pollInterval = setInterval(() => this.refreshAll(), window.REPORT_LIVE_POLL_SECONDS * 1000);
        },

        changeMode() {
            this.liveHistory = [];
            this.destroyCharts();
            this.hasRealtimeSnapshot = false;
            this.realtimeSnapshotKey = '';
            this.realtime = {
                ...this.realtime,
                mode: this.mode,
                status: 'unavailable',
                lastUpdated: '',
                staleMessage: '',
                cards: [],
                sources: [],
                dispositions: [],
            };
            this.startPolling();
            this.refreshAll();
        },

        applyRealtime(data) {
            const metrics = data.metrics || {};
            const rolling = data.rolling || {};
            const today = data.today || {};
            const isToday = this.mode === 'today';
            const status = data.freshness?.status || data.availability?.status || 'unavailable';
            const normalizedStatus = String(status).toLowerCase();
            const realtimeSnapshotKey = this.mode + '|' + this.filters.crm_campaign;
            const shouldPreserveLastGood = this.hasRealtimeSnapshot
                && this.realtime.mode === this.mode
                && this.realtimeSnapshotKey === realtimeSnapshotKey
                && ['offline', 'stale', 'unavailable'].includes(normalizedStatus);

            if (shouldPreserveLastGood) {
                this.realtime = {
                    ...this.realtime,
                    status: 'stale',
                    staleMessage: data.availability?.message || 'The last live snapshot could not be refreshed. Retry to request a fresh snapshot.',
                    sources: Object.entries(data.sources || {}).map(([key, source]) => ({
                        key,
                        label: this.humanizeLabel(key),
                        status: source.status || 'unavailable',
                    })),
                };
                this.dashboard.availability = {
                    ...this.dashboard.availability,
                    ...(data.availability || {}),
                    status: 'stale',
                    message: data.availability?.message || this.realtime.staleMessage,
                };

                return;
            }
            const numberOrDash = (value) => value === null || value === undefined ? 'N/A' : this.formatNumber(value);
            const cards = isToday ? [
                { key: 'today-total', label: "Today's Calls", value: numberOrDash(today.total_calls), scope: this.cleanReportText(today.label || 'Midnight to Now') },
                { key: 'today-answered', label: 'Answered', value: numberOrDash(today.answered), scope: this.cleanReportText(today.label || 'Midnight to Now') },
                { key: 'today-rate', label: 'Answer Rate', value: this.formatPercent(today.answer_rate), scope: this.cleanReportText(today.label || 'Midnight to Now') },
                { key: 'live-calls', label: 'Live Calls', value: numberOrDash(metrics.live_calls), scope: 'Current snapshot' },
                { key: 'waiting', label: 'Waiting Calls', value: numberOrDash(metrics.calls_waiting), scope: 'Current snapshot' },
                { key: 'available', label: 'Available Agents', value: numberOrDash(metrics.available_agents), scope: 'Current snapshot' },
            ] : [
                { key: 'live-calls', label: 'Live Calls', value: numberOrDash(metrics.live_calls), scope: 'Current snapshot' },
                { key: 'active-agents', label: 'Active Agents', value: numberOrDash(metrics.active_agents), scope: 'Current snapshot' },
                { key: 'available', label: 'Available Agents', value: numberOrDash(metrics.available_agents), scope: 'Current snapshot' },
                { key: 'waiting', label: 'Waiting Calls', value: numberOrDash(metrics.calls_waiting), scope: 'Current snapshot' },
                { key: 'rolling-answered', label: 'Answered', value: numberOrDash(rolling.answered), scope: this.cleanReportText(rolling.label || 'Rolling Window') },
                { key: 'rolling-rate', label: 'Answer Rate', value: this.formatPercent(rolling.answer_rate), scope: this.cleanReportText(rolling.label || 'Rolling Window') },
            ];
            this.realtime = {
                mode: this.mode,
                snapshotKey: realtimeSnapshotKey,
                status: normalizedStatus,
                scopeLabel: this.cleanReportText(data.time_scope?.label || rolling.label || 'Rolling Operational Window'),
                rollingScope: this.cleanReportText(rolling.label || 'Rolling Metrics Unavailable'),
                lastUpdated: data.freshness?.last_success_at ? new Date(data.freshness.last_success_at).toLocaleTimeString() : new Date().toLocaleTimeString(),
                staleMessage: data.freshness?.status === 'stale' ? 'Live data is stale. Retry to request a fresh snapshot.' : (data.availability?.message || ''),
                cards,
                sources: Object.entries(data.sources || {}).map(([key, source]) => ({ key, label: this.humanizeLabel(key), status: source.status || 'unavailable' })),
                dispositions: Object.entries((isToday ? today.dispositions : rolling.dispositions) || {}).map(([code, count]) => ({ code, count: this.formatNumber(count) })),
            };
            this.hasRealtimeSnapshot = true;
            this.realtimeSnapshotKey = realtimeSnapshotKey;
            this.dashboard.availability = {
                ...this.dashboard.availability,
                ...(data.availability || {}),
                status: normalizedStatus,
            };
            this.liveHistory = [...this.liveHistory, {
                label: new Date().toLocaleTimeString(),
                active: metrics.live_calls ?? null,
                waiting: metrics.calls_waiting ?? null,
                answered: rolling.answered ?? null,
                abandoned: rolling.abandoned ?? null,
            }].slice(-60);
        },

        applyDashboard(data) {
            const availabilityStatus = String(data.availability?.status || 'unavailable').toLowerCase();
            const dashboardSnapshotKey = [
                data.filters?.crm_campaign || this.filters.crm_campaign,
                this.filters.campaigns,
                this.filters.query_date,
                this.filters.end_date,
                this.filters.timezone,
                this.filters.disposition_scope,
                this.filters.comparison,
            ].join('|');
            if (this.hasDashboardSnapshot
                && this.dashboardSnapshotKey === dashboardSnapshotKey
                && availabilityStatus !== 'live') {
                this.dashboard.availability = {
                    ...this.dashboard.availability,
                    ...(data.availability || {}),
                    status: 'stale',
                    message: data.availability?.message || 'The last successful report snapshot is being shown while VICIdial refreshes.',
                };

                return;
            }
            const summary = data.summary || {};
            const callVolume = data.call_volume || {};
            const campaigns = Array.isArray(data.campaigns) ? data.campaigns : [];
            const dispositions = data.dispositions || { labels: [], values: [], percentages: [] };
            const dispositionRows = Array.isArray(data.disposition_rows) ? data.disposition_rows : [];
            const agents = Array.isArray(data.agents) ? data.agents : [];

            this.dashboard.availability = data.availability || this.dashboard.availability;
            if (Array.isArray(data.campaign_scope?.campaign_codes)) {
                this.mappedCampaigns = data.campaign_scope.campaign_codes;
                if (this.filters.campaigns !== '---ALL---' && !this.mappedCampaigns.includes(this.filters.campaigns)) {
                    this.filters.campaigns = '---ALL---';
                }
            }
            this.dashboard.scopeLabel = this.dispositionScopeLabel(this.filters.disposition_scope);
            this.dashboard.overview = {
                ...this.dashboard.overview,
                campaign: data.filters?.crm_campaign || this.filters.crm_campaign,
                totalCalls: summary.total_calls,
                answeredCalls: summary.answered_calls,
                answerRate: summary.answer_rate,
                contactRate: summary.contact_rate ?? null,
                averageTalkTimeSeconds: summary.average_talk_time_seconds ?? null,
                agentsWithActivity: summary.agents_with_activity,
                callsPerAgent: summary.calls_per_agent,
            };
            this.dashboard.status = {
                rows: campaigns.map((row, index) => ({
                    key: (row.campaign || 'campaign') + '-' + index,
                    label: row.campaign || 'Unknown',
                    total: row.total_calls ?? null,
                    answered: row.answered_calls ?? null,
                    answerRate: row.answer_rate ?? null,
                    topStatus: 'N/A',
                    peakHourLabel: 'N/A',
                })),
                hourlyLabels: callVolume.labels || [],
                hourlyValues: callVolume.values || [],
                hourlyState: callVolume.state || 'unavailable',
                statusLabels: Object.keys(data.status_totals || {}),
                statusValues: Object.values(data.status_totals || {}),
                statusState: data.status_state || (Object.keys(data.status_totals || {}).length ? 'data' : 'unavailable'),
                summary: {
                    totalCalls: summary.total_calls,
                    answeredCalls: summary.answered_calls,
                    answerRate: summary.answer_rate,
                    topStatus: 'N/A',
                    topHour: 'N/A',
                },
            };
            this.dashboard.status.rows.forEach((statusRow, index) => {
                const source = campaigns[index];
                statusRow.topStatus = source?.top_status ?? statusRow.topStatus;
                statusRow.peakHourLabel = source?.peak_hour ?? statusRow.peakHourLabel;
            });
            this.dashboard.agents = {
                rows: agents.map((row, index) => ({
                    ...row,
                    key: (row.user || 'agent') + '-' + index,
                    total_talk_time: this.formatDuration(row.total_talk_time_seconds),
                    avg_talk_time: this.formatDuration(row.avg_talk_time_seconds),
                    total_wait_time: this.formatDuration(row.total_wait_time_seconds),
                    pause_pct: row.pause_pct === null ? 'N/A' : String(row.pause_pct) + '%',
                })),
                callsLabels: agents.slice(0, 10).map((row) => row.full_name || row.user),
                callsValues: agents.slice(0, 10).map((row) => row.calls ?? null),
                summary: {
                    agentCount: summary.agents_with_activity,
                    totalCalls: data.agent_summary?.total_calls,
                    totalTalkTime: this.formatDuration(data.agent_summary?.total_talk_time_seconds),
                    totalPauseTime: this.formatDuration(data.agent_summary?.total_pause_time_seconds),
                    avgTalkTime: this.formatDuration(summary.average_talk_time_seconds),
                    topAgent: agents[0]?.full_name || agents[0]?.user || 'N/A',
                },
            };
            this.dashboard.dispo = {
                rows: dispositionRows.map((row, index) => ({
                    key: (row.campaign || 'campaign') + '-' + index,
                    label: row.campaign || 'Unknown',
                    totalCalls: row.total_calls ?? null,
                    topDisposition: row.top_disposition || 'N/A',
                    breakdownSummary: (row.metrics || []).slice(0, 3)
                        .map((metric) => metric.label + ': ' + this.formatNumber(metric.value))
                        .join(', ') || 'No breakdown data',
                })),
                labels: dispositions.labels || [],
                values: dispositions.values || [],
                percentages: dispositions.percentages || [],
                state: dispositions.state || data.disposition_summary?.state || 'unavailable',
                summary: {
                    totalCalls: data.disposition_summary?.total_calls ?? null,
                    topDisposition: dispositions.labels?.[0] || 'N/A',
                    topDispositionCount: dispositions.values?.[0] ?? null,
                    scopeLabel: this.dispositionScopeLabel(this.filters.disposition_scope),
                },
            };
            this.dashboard.dispo.rows.forEach((dispositionRow, index) => {
                const source = dispositionRows[index];
                const metrics = Array.isArray(source?.metrics) ? source.metrics : [];
                dispositionRow.topDisposition = source?.top_disposition ?? dispositionRow.topDisposition;
                if (metrics.length === 0 || metrics.every((metric) => metric.value === null || metric.value === undefined)) {
                    dispositionRow.breakdownSummary = 'Unavailable';
                }
            });
            this.dashboard.funnel = Array.isArray(data.funnel) ? data.funnel : [];
            const time = data.time_distribution || {};
            this.dashboard.timeDistribution = [
                { key: 'talk', label: 'Talk', seconds: time.talk_seconds ?? null },
                { key: 'pause', label: 'Pause', seconds: time.pause_seconds ?? null },
                { key: 'ready', label: 'Ready', seconds: time.ready_seconds ?? null },
                { key: 'other', label: 'Other', seconds: time.other_seconds ?? null },
            ];
            this.dashboard.comparison = this.normalizeComparison(data.comparison);
            this.hasDashboardSnapshot = availabilityStatus === 'live';
            this.dashboardSnapshotKey = dashboardSnapshotKey;
        },

        normalizeComparison(comparison = {}) {
            if (!comparison.enabled) {
                return { enabled: false, periodLabel: '', availabilityLabel: '', cards: [] };
            }
            const labels = {
                total_calls: 'Total Calls',
                answered_calls: 'Answered',
                answer_rate: 'Answer Rate',
                contact_rate: 'Contact Rate',
                average_talk_time_seconds: 'Average Talk',
                agents_with_activity: 'Agents With Activity',
                calls_per_agent: 'Calls per Agent',
            };
            const cards = Object.entries(comparison.metrics || {}).slice(0, 4).map(([key, metric]) => {
                const change = metric.change;
                const isRate = metric.unit === 'rate';
                const changeLabel = change === null
                    ? 'No comparable baseline'
                    : (change >= 0 ? 'Increase of ' : 'Decrease of ') + Math.abs(change).toFixed(1)
                        + (isRate ? ' percentage points' : '%') + ' versus previous';

                return {
                    key,
                    label: labels[key] || key,
                    value: metric.current === null ? 'N/A' : (isRate ? this.formatPercent(metric.current) : this.formatNumber(metric.current)),
                    changeLabel,
                    tone: change === null ? 'text-[var(--color-on-surface-dim)]' : (change >= 0 ? 'text-[var(--color-success)]' : 'text-[var(--color-danger)]'),
                };
            });

            return {
                enabled: true,
                periodLabel: comparison.period ? comparison.period.start + ' to ' + comparison.period.end : 'previous period',
                availabilityLabel: this.humanizeLabel(comparison.availability?.status || 'unknown'),
                cards,
            };
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

        /* Legacy raw VICIdial normalizers are retained as inert compatibility code.
         * refreshAll uses the normalized dashboard API contract exclusively.
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
        */

        dispositionScopeLabel(scope) {
            const option = this.dispositionScopeOptions.find((entry) => entry.value === scope);
            return option?.label || 'All Dispositions';
        },

        async renderCharts() {
            if (this.mode !== 'historical') {
                await this.renderLiveChart();
                return;
            }
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

            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const textColor = isDark ? '#a1a1aa' : '#52525b';
            const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

            if (statusHourlyEl && this.dashboard.status.hourlyLabels.length) {
                const chart = new ApexCharts(statusHourlyEl, {
                    series: [{ name: 'Calls', data: this.dashboard.status.hourlyValues }],
                    chart: { type: 'area', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                    colors: ['#e91e8c'],
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.32, opacityTo: 0.05 } },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: { categories: this.dashboard.status.hourlyLabels, labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'status-hourly', chart);
                await chart.render();
            }

            if (statusMixEl && this.dashboard.status.statusLabels.length) {
                const chart = new ApexCharts(statusMixEl, {
                    series: this.dashboard.status.statusValues,
                    chart: { type: 'donut', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                    labels: this.dashboard.status.statusLabels,
                    colors: ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7', '#14b8a6'],
                    dataLabels: { enabled: false },
                    legend: { position: 'bottom', labels: { colors: textColor } },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                            },
                        },
                    },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    theme: { mode: isDark ? 'dark' : 'light' },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'status-mix', chart);
                await chart.render();
            }

            if (agentCallsEl && this.dashboard.agents.callsLabels.length) {
                const chart = new ApexCharts(agentCallsEl, {
                    series: [{ name: 'Calls', data: this.dashboard.agents.callsValues }],
                    chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                    colors: ['#3b82f6'],
                    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%' } },
                    xaxis: { categories: this.dashboard.agents.callsLabels, labels: { style: { colors: textColor, fontSize: '11px' } } },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' }, maxWidth: 160 } },
                    grid: { borderColor: gridColor, strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    dataLabels: { enabled: false },
                    theme: { mode: isDark ? 'dark' : 'light' },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'agent-calls', chart);
                await chart.render();
            }

            if (dispoEl && this.dashboard.dispo.labels.length) {
                const chart = new ApexCharts(dispoEl, {
                    series: [{ name: 'Calls', data: this.dashboard.dispo.values }],
                    chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                    labels: this.dashboard.dispo.labels,
                    colors: ['#3b82f6'],
                    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '60%' } },
                    xaxis: { categories: this.dashboard.dispo.labels, labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                    yaxis: { labels: { style: { colors: textColor, fontSize: '11px' }, maxWidth: 160 } },
                    dataLabels: { enabled: true, style: { colors: [textColor] } },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    theme: { mode: isDark ? 'dark' : 'light' },
                });
                window.crmCharts?.register?.(CHART_GROUP, 'dispo-breakdown', chart);
                await chart.render();
            }

            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            window.crmCharts?.resizeGroup?.(CHART_GROUP);
        },

        async renderLiveChart() {
            const ApexCharts = await window.ApexChartsLoader?.() ?? null;
            const chartElement = document.getElementById('chart-live-activity');
            if (!ApexCharts || !chartElement || this.liveHistory.length === 0) {
                return;
            }

            const labels = this.liveHistory.map((point) => point.label);
            const series = [
                { name: 'Live calls', data: this.liveHistory.map((point) => point.active) },
                { name: 'Waiting calls', data: this.liveHistory.map((point) => point.waiting) },
            ];
            if (this.liveChart) {
                await this.liveChart.updateOptions({ xaxis: { categories: labels } }, false, false);
                await this.liveChart.updateSeries(series, true);
                return;
            }

            chartElement.innerHTML = '';
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const textColor = isDark ? '#a1a1aa' : '#52525b';
            const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.liveChart = new ApexCharts(chartElement, {
                series,
                chart: { type: 'line', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', animations: { enabled: !reduceMotion, dynamicAnimation: { speed: 350 } } },
                colors: ['#22c55e', '#f59e0b'],
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: labels, labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                yaxis: { min: 0, labels: { style: { colors: textColor, fontSize: '11px' } } },
                grid: { borderColor: gridColor, strokeDashArray: 3 },
                legend: { labels: { colors: textColor } },
                tooltip: { theme: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            window.crmCharts?.register?.(CHART_GROUP, 'live-activity', this.liveChart);
            await this.liveChart.render();
        },

        /* Legacy raw response parsing is intentionally disabled; the backend
         * parser is the sole source of historical report values.
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
        */

        secondsToDuration(seconds) {
            const total = Math.max(0, Math.round(Number(seconds) || 0));
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const secs = total % 60;

            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },

        humanizeLabel(value) {
            const acronyms = {
                api: 'API',
                crm: 'CRM',
                id: 'ID',
                ivr: 'IVR',
                sip: 'SIP',
                vicidial: 'VICIdial',
            };
            const words = String(value ?? '')
                .trim()
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .split(' ')
                .filter(Boolean);

            if (!words.length) {
                return 'Unknown';
            }

            return words.map((word) => {
                const normalized = word.toLowerCase();

                return acronyms[normalized] || normalized.charAt(0).toUpperCase() + normalized.slice(1);
            }).join(' ');
        },

        cleanReportText(value) {
            return String(value ?? '')
                .replace(/\s*→\s*/g, ' to ')
                .replace(/\s*·\s*/g, '; ')
                .replace(/\s+\|\s+/g, ', ')
                .replace(/\s+/g, ' ')
                .trim();
        },

        formatDuration(seconds) {
            if (seconds !== null && seconds !== undefined && seconds !== '' && !Number.isFinite(Number(seconds))) {
                return 'Unavailable';
            }

            if (seconds === null || seconds === undefined || seconds === '') {
                return 'N/A';
            }

            return this.secondsToDuration(seconds);
        },

        reportSectionMessage(state, label) {
            if (state === 'loading') {
                return 'Loading ' + label.toLowerCase() + '...';
            }
            if (state === 'empty' || state === 'confirmed_zero') {
                return 'No ' + label.toLowerCase() + ' was returned for this scope.';
            }
            if (state === 'unsupported' || state === 'parse_failure') {
                return label + ' unavailable from the VICIdial response.';
            }

            return label + ' unavailable. Retry to request a fresh report.';
        },

        reportSectionTitle(state, label) {
            if (state === 'loading') {
                return 'Loading ' + label;
            }
            if (state === 'empty' || state === 'confirmed_zero') {
                return 'No ' + label + ' for This Scope';
            }
            if (state === 'unsupported' || state === 'parse_failure') {
                return label + ' Unavailable';
            }

            return label + ' Needs Attention';
        },

        reportSectionDescription(state, label) {
            if (state === 'loading') {
                return 'The report is still being requested. This message will update when a response arrives.';
            }
            if (state === 'empty' || state === 'confirmed_zero') {
                return 'No ' + label.toLowerCase() + ' was returned for the selected campaign and date range.';
            }
            if (state === 'unsupported' || state === 'parse_failure') {
                return 'The VICIdial response did not include usable ' + label.toLowerCase() + '. Retry or check the report connection.';
            }

            return 'The report could not provide ' + label.toLowerCase() + '. Retry to request a fresh response.';
        },

        formatNumber(value) {
            if (value === null || value === undefined || value === '') {
                return 'N/A';
            }

            const number = Number(value);
            if (!Number.isFinite(number)) {
                return 'N/A';
            }

            return new Intl.NumberFormat().format(number);
        },

        formatPercent(value) {
            if (value === null || value === undefined || value === '') {
                return 'N/A';
            }

            const number = Number(value);
            if (!Number.isFinite(number)) {
                return 'N/A';
            }

            return `${number.toFixed(1)}%`;
        },
    };
};
</script>
@endpush
