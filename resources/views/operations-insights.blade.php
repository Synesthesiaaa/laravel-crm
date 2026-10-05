<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $brandName = trim((string) data_get($branding, 'name', config('app.name', 'CRM'))) ?: 'CRM';
        $faviconUrl = data_get($branding, 'favicon_path')
            ? data_get($branding, 'favicon_url', '/favicon.ico')
            : '/favicon.ico';
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="Campaign performance, sales activity, and call reporting in one streamlined operations view.">
    <script>
        (function () {
            var theme = 'dark';
            try { theme = localStorage.getItem('theme') || 'dark'; } catch (_) {}
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <title>Operations Insights | {{ $brandName }}</title>
    <link rel="icon" href="{{ $faviconUrl }}">
    @vite(['resources/css/app.css', 'resources/js/operations-insights.js'])
    <style>
        [x-cloak] { display: none !important; }
        .operations-shell { width: min(1500px, calc(100% - 2rem)); margin-inline: auto; }
        .operations-panel { border: 1px solid var(--color-border); background: var(--color-surface-1); border-radius: 1rem; box-shadow: 0 18px 48px rgb(0 0 0 / .08); }
        .operations-metric { border: 1px solid var(--color-border); background: var(--color-surface); border-radius: .85rem; padding: 1rem; min-width: 0; }
        .operations-label { color: var(--color-on-surface-dim); font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .operations-value { margin-top: .35rem; color: var(--color-on-surface); font-size: clamp(1.35rem, 2.5vw, 2rem); font-weight: 750; line-height: 1.05; }
        .operations-table th { color: var(--color-on-surface-dim); font-size: .72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .operations-table th, .operations-table td { padding: .75rem 1rem; border-bottom: 1px solid var(--color-border); text-align: left; white-space: nowrap; }
        .operations-table tbody tr:last-child td { border-bottom: 0; }
    </style>
</head>
<body class="min-h-screen bg-[var(--color-surface)] text-[var(--color-on-surface)] antialiased">
    <div
        x-data="operationsInsightsDashboard({ selectedCampaign: @js($selectedCampaign) })"
        x-init="init()"
        @beforeunload.window="cleanup()"
        class="min-h-screen"
    >
        <header class="sticky top-0 z-40 border-b border-[var(--color-border)] bg-[color-mix(in_srgb,var(--color-surface)_92%,transparent)] backdrop-blur-xl">
            <div class="operations-shell flex min-h-16 flex-wrap items-center justify-between gap-3 py-3">
                <div class="flex min-w-0 items-center gap-4">
                    <a href="{{ route('home') }}" class="shrink-0 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" aria-label="{{ $brandName }} home">
                        <x-brand :branding="$branding" class="max-w-52 [&>span:last-child]:truncate" />
                    </a>
                    <div class="hidden h-8 w-px bg-[var(--color-border)] sm:block"></div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[var(--color-on-surface)]">Operations Insights</p>
                        <p class="truncate text-xs text-[var(--color-on-surface-dim)]">Campaign Performance &amp; Reporting</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="operations-shell py-5 sm:py-7">
            <section class="operations-panel overflow-hidden">
                <div class="border-b border-[var(--color-border)] p-4 sm:p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[.12em] text-[var(--color-primary)]">Performance overview</p>
                            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Operations Insights</h1>
                            <p class="mt-1 max-w-3xl text-sm text-[var(--color-on-surface-muted)]">See campaign performance, sales activity, and call results in one place.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-[minmax(190px,1fr)_auto]">
                            <label class="grid gap-1.5 text-xs font-semibold text-[var(--color-on-surface-muted)]" for="operations-campaign">
                                Campaign
                                <select id="operations-campaign" x-model="campaign" @change="refreshAll()" class="min-h-10 rounded-lg border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-3 text-sm text-[var(--color-on-surface)] outline-none focus:border-[var(--color-primary)]">
                                    @foreach ($campaigns as $campaign)
                                        <option value="{{ $campaign['code'] }}">{{ $campaign['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button" @click="refreshAll()" :disabled="loading" class="inline-flex min-h-10 items-center justify-center gap-2 self-end rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-primary-foreground)] transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                                <x-icon name="refresh-cw" class="h-4 w-4" />
                                <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="inline-flex w-fit rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-1" role="tablist" aria-label="Dashboard sections">
                            <button type="button" @click="section = 'dashboard'; renderChartsSoon()" :class="section === 'dashboard' ? 'bg-[var(--color-surface-2)] text-[var(--color-on-surface)] shadow-sm' : 'text-[var(--color-on-surface-muted)]'" class="rounded-lg px-4 py-2 text-sm font-semibold transition" role="tab">Overview</button>
                            <button type="button" @click="section = 'reports'; renderChartsSoon()" :class="section === 'reports' ? 'bg-[var(--color-surface-2)] text-[var(--color-on-surface)] shadow-sm' : 'text-[var(--color-on-surface-muted)]'" class="rounded-lg px-4 py-2 text-sm font-semibold transition" role="tab">Call Reports</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[var(--color-on-surface-dim)]">
                            <span x-show="data?.context?.campaign?.name" x-text="data?.context?.campaign?.name"></span>
                            <span x-show="lastUpdated" x-text="lastUpdated ? 'Updated ' + lastUpdated : ''"></span>
                            <span x-show="mode !== 'historical'" class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="reports?.availability?.status === 'live' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                <span x-text="humanize(reports?.availability?.status || 'unavailable')"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <div x-show="error" x-cloak class="border-b border-[var(--color-danger)]/20 bg-[var(--color-danger)]/10 px-5 py-3 text-sm text-[var(--color-danger)]">
                    <span class="font-semibold">Unable to refresh this view.</span>
                    <span x-text="error"></span>
                </div>

                <div x-show="loading && !data" class="grid min-h-[420px] place-items-center p-8">
                    <div class="text-center">
                        <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-[var(--color-border-strong)] border-t-[var(--color-primary)]"></div>
                        <p class="mt-3 text-sm text-[var(--color-on-surface-muted)]">Loading campaign metrics…</p>
                    </div>
                </div>

                <div x-show="data" x-cloak>
                    <section x-show="section === 'dashboard'" class="space-y-5 p-4 sm:p-5">
                        <div x-show="sectionVisible('kpis')" class="flex flex-col gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold">Sales period</p>
                                <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Choose the date and time used for the sales totals below.</p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label class="grid gap-1 text-xs font-semibold text-[var(--color-on-surface-muted)]">Date<input type="date" x-model="salesDate" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-2.5 py-2 text-sm"></label>
                                <label class="grid gap-1 text-xs font-semibold text-[var(--color-on-surface-muted)]">Start<input type="time" x-model="salesStart" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-2.5 py-2 text-sm"></label>
                                <label class="grid gap-1 text-xs font-semibold text-[var(--color-on-surface-muted)]">End<input type="time" x-model="salesEnd" @change="refreshAll()" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-2.5 py-2 text-sm"></label>
                            </div>
                        </div>

                        <div x-show="sectionVisible('kpis')" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="operations-metric"><p class="operations-label">Sales</p><p class="operations-value" x-text="number(dashboard?.kpis?.sales)"></p><p class="mt-2 text-xs text-[var(--color-on-surface-dim)]" x-text="salesWindowLabel()"></p></div>
                            <div class="operations-metric" x-show="amountVisible() && dashboard?.kpis?.sales_amount !== undefined"><p class="operations-label">Sales amount</p><p class="operations-value" x-text="money(dashboard?.kpis?.sales_amount)"></p><p class="mt-2 text-xs text-[var(--color-on-surface-dim)]">Sales value for this period</p></div>
                            <div class="operations-metric"><p class="operations-label">Top agent</p><p class="operations-value truncate" x-text="dashboard?.kpis?.top_agent || '—'"></p><p class="mt-2 text-xs text-[var(--color-on-surface-dim)]"><span x-text="number(dashboard?.kpis?.top_agent_sales)"></span> sales</p></div>
                            <div class="operations-metric"><p class="operations-label">Month to date</p><p class="operations-value" x-text="number(dashboard?.summary?.summary?.current?.count)"></p><p class="mt-2 text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard?.summary?.period?.current?.label || 'Current period'"></p></div>
                        </div>

                        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
                            <article x-show="sectionVisible('activity')" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div><h2 class="font-semibold">Activity over time</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Campaign activity over the last 24 hours</p></div>
                                    <span class="rounded-full bg-[var(--color-surface-2)] px-2.5 py-1 text-xs font-semibold text-[var(--color-on-surface-muted)]">24 hours</span>
                                </div>
                                <div id="operations-activity-chart" class="mt-4 min-h-[280px]"></div>
                            </article>

                            <article x-show="sectionVisible('leaderboard')" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                                <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Sales leaderboard</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Current selected sales window</p></div><span class="text-xs text-[var(--color-on-surface-dim)]" x-text="(dashboard?.kpis?.agent_leaderboard || []).length + ' agents'"></span></div>
                                <div class="mt-3 max-h-[300px] overflow-auto rounded-lg border border-[var(--color-border)]">
                                    <table class="operations-table w-full text-sm">
                                        <thead class="sticky top-0 bg-[var(--color-surface-1)]"><tr><th>Agent</th><th>Sales</th><th x-show="amountVisible('tables')">Amount</th></tr></thead>
                                        <tbody>
                                            <template x-for="row in dashboard?.kpis?.agent_leaderboard || []" :key="row.agent"><tr><td class="font-medium" x-text="row.agent"></td><td x-text="number(row.sales_count)"></td><td x-show="amountVisible('tables')" x-text="money(row.sales_amount)"></td></tr></template>
                                            <tr x-show="!(dashboard?.kpis?.agent_leaderboard || []).length"><td colspan="3" class="text-center text-[var(--color-on-surface-dim)]">No sales activity in this window.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        </div>

                        <article x-show="sectionVisible('campaign_report')" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="font-semibold">Daily campaign totals</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Daily results by agent for the selected campaign</p></div><span class="text-xs text-[var(--color-on-surface-dim)]" x-text="dashboard?.campaign_report?.date || ''"></span></div>
                            <div class="mt-3 overflow-auto rounded-lg border border-[var(--color-border)]">
                                <table class="operations-table w-full text-sm">
                                    <thead class="bg-[var(--color-surface-1)]"><tr><th>Agent</th><template x-for="form in dashboard?.campaign_report?.forms || []" :key="form.code"><th x-text="form.name"></th></template><th>Total</th></tr></thead>
                                    <tbody>
                                        <template x-for="row in dashboard?.campaign_report?.daily || []" :key="row.agent"><tr><td class="font-medium" x-text="row.agent"></td><template x-for="form in dashboard?.campaign_report?.forms || []" :key="form.code"><td x-text="number(row.counts?.[form.code])"></td></template><td class="font-semibold" x-text="number(row.total_count)"></td></tr></template>
                                        <tr x-show="!(dashboard?.campaign_report?.daily || []).length"><td :colspan="(dashboard?.campaign_report?.forms || []).length + 2" class="text-center text-[var(--color-on-surface-dim)]">No campaign activity for this date.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>

                    <section x-show="section === 'reports'" class="space-y-5 p-4 sm:p-5">
                        <div class="flex flex-col gap-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <p class="text-sm font-semibold">Report view</p>
                                <div class="mt-2 inline-flex flex-wrap rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-1">
                                    <button type="button" @click="setMode('historical')" :class="mode === 'historical' ? 'bg-[var(--color-surface-2)] text-[var(--color-on-surface)] shadow-sm' : 'text-[var(--color-on-surface-muted)]'" class="rounded-md px-3 py-1.5 text-xs font-semibold">Past Performance</button>
                                    <button type="button" @click="setMode('live')" :class="mode === 'live' ? 'bg-[var(--color-surface-2)] text-[var(--color-on-surface)] shadow-sm' : 'text-[var(--color-on-surface-muted)]'" class="rounded-md px-3 py-1.5 text-xs font-semibold">Live Activity</button>
                                    <button type="button" @click="setMode('today')" :class="mode === 'today' ? 'bg-[var(--color-surface-2)] text-[var(--color-on-surface)] shadow-sm' : 'text-[var(--color-on-surface-muted)]'" class="rounded-md px-3 py-1.5 text-xs font-semibold">Today</button>
                                </div>
                            </div>
                            <div x-show="mode === 'historical'" class="grid gap-2 sm:grid-cols-2">
                                <label class="grid gap-1 text-xs font-semibold text-[var(--color-on-surface-muted)]">From<input type="date" x-model="queryDate" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-2.5 py-2 text-sm"></label>
                                <label class="grid gap-1 text-xs font-semibold text-[var(--color-on-surface-muted)]">To<input type="date" x-model="endDate" @change="refreshAll()" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-2.5 py-2 text-sm"></label>
                            </div>
                        </div>

                        <template x-if="mode === 'historical'">
                            <div class="space-y-5">
                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                                    <div class="operations-metric"><p class="operations-label">Total calls</p><p class="operations-value" x-text="number(reports?.summary?.total_calls)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Answered</p><p class="operations-value" x-text="number(reports?.summary?.answered_calls)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Answer rate</p><p class="operations-value" x-text="percent(reports?.summary?.answer_rate)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Contact rate</p><p class="operations-value" x-text="percent(reports?.summary?.contact_rate)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Calls / agent</p><p class="operations-value" x-text="decimal(reports?.summary?.calls_per_agent)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Avg talk</p><p class="operations-value" x-text="duration(reports?.summary?.average_talk_time_seconds)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Agents active</p><p class="operations-value" x-text="number(reports?.summary?.agents_with_activity)"></p></div>
                                </div>

                                <div x-show="reports?.availability?.status !== 'live'" class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-3 text-sm text-[var(--color-on-surface-muted)]"><span class="font-semibold" x-text="humanize(reports?.availability?.status || 'unavailable')"></span><span x-show="reports?.availability?.message"> · </span><span x-text="reports?.availability?.message || ''"></span></div>

                                <div class="grid gap-5 xl:grid-cols-2">
                                    <article class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4"><div><h2 class="font-semibold">Call volume</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Calls across the selected period</p></div><div id="operations-report-call-chart" class="mt-4 min-h-[280px]"></div></article>
                                    <article class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4"><div><h2 class="font-semibold">Call outcomes</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Most common call results for the selected period</p></div><div id="operations-report-disposition-chart" class="mt-4 min-h-[280px]"></div></article>
                                </div>

                                <article class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                                    <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Agent performance</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Call activity and talk time by agent</p></div><span class="text-xs text-[var(--color-on-surface-dim)]" x-text="(reports?.agents || []).length + ' agents'"></span></div>
                                    <div class="mt-3 overflow-auto rounded-lg border border-[var(--color-border)]">
                                        <table id="operations-report-agent-table" class="operations-table w-full text-sm">
                                            <thead class="bg-[var(--color-surface-1)]"><tr><th>Agent</th><th>Calls</th><th>Avg talk</th><th>Total talk</th><th>Pause</th></tr></thead>
                                            <tbody><template x-for="(row, index) in reports?.agents || []" :key="row.key || row.user || index"><tr><td class="font-medium" x-text="agentName(row)"></td><td x-text="number(row.calls)"></td><td x-text="duration(row.average_talk_time_seconds ?? row.avg_talk_time_seconds)"></td><td x-text="duration(row.total_talk_time_seconds)"></td><td x-text="duration(row.total_pause_time_seconds)"></td></tr></template><tr x-show="!(reports?.agents || []).length"><td colspan="5" class="text-center text-[var(--color-on-surface-dim)]">No agent rows are available for this period.</td></tr></tbody>
                                        </table>
                                    </div>
                                </article>
                            </div>
                        </template>

                        <template x-if="mode !== 'historical'">
                            <div class="space-y-5">
                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                                    <div class="operations-metric"><p class="operations-label">Active agents</p><p class="operations-value" x-text="number(reports?.metrics?.active_agents)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Available</p><p class="operations-value" x-text="number(reports?.metrics?.available_agents)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Paused</p><p class="operations-value" x-text="number(reports?.metrics?.paused_agents)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Live calls</p><p class="operations-value" x-text="number(reports?.metrics?.live_calls)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Calls waiting</p><p class="operations-value" x-text="number(reports?.metrics?.calls_waiting)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Today calls</p><p class="operations-value" x-text="number(reports?.today?.total_calls)"></p></div>
                                    <div class="operations-metric"><p class="operations-label">Today answer rate</p><p class="operations-value" x-text="percent(reports?.today?.answer_rate)"></p></div>
                                </div>

                                <div class="grid gap-5 xl:grid-cols-2">
                                    <article class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4"><h2 class="font-semibold">Recent call activity</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]" x-text="reports?.rolling?.label || 'Recent activity'"></p><div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4"><div><p class="operations-label">Started</p><p class="mt-1 text-xl font-semibold" x-text="number(reports?.rolling?.calls_initiated)"></p></div><div><p class="operations-label">Answered</p><p class="mt-1 text-xl font-semibold" x-text="number(reports?.rolling?.answered)"></p></div><div><p class="operations-label">Answer rate</p><p class="mt-1 text-xl font-semibold" x-text="percent(reports?.rolling?.answer_rate)"></p></div><div><p class="operations-label">Avg talk</p><p class="mt-1 text-xl font-semibold" x-text="duration(reports?.rolling?.average_talk_seconds)"></p></div></div></article>
                                    <article class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4"><h2 class="font-semibold">Data status</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Availability of the reporting data</p><div class="mt-4 grid gap-2 sm:grid-cols-2"><template x-for="source in reports?.sources || []" :key="source.key"><div class="flex items-center justify-between gap-3 rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm"><span x-text="humanize(source.key)"></span><span class="text-xs font-semibold" :class="source.status === 'healthy' ? 'text-emerald-500' : 'text-amber-500'" x-text="humanize(source.status)"></span></div></template><p x-show="!(reports?.sources || []).length" class="text-sm text-[var(--color-on-surface-dim)]">No data status is available.</p></div></article>
                                </div>

                                <article class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                                    <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Team status</h2><p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Current agent activity for this campaign</p></div><span class="text-xs text-[var(--color-on-surface-dim)]" x-text="(reports?.agents || []).length + ' agents'"></span></div>
                                    <div class="mt-3 overflow-auto rounded-lg border border-[var(--color-border)]"><table class="operations-table w-full text-sm"><thead class="bg-[var(--color-surface-1)]"><tr><th>Agent</th><th>Status</th><th>Calls today</th><th>Avg handle</th><th>Avg wait</th></tr></thead><tbody><template x-for="(row, index) in reports?.agents || []" :key="row.name || index"><tr><td class="font-medium" x-text="row.name || '—'"></td><td x-text="row.state_label || humanize(row.state)"></td><td x-text="number(row.calls_today)"></td><td x-text="duration(row.avg_handle)"></td><td x-text="duration(row.avg_wait)"></td></tr></template><tr x-show="!(reports?.agents || []).length"><td colspan="5" class="text-center text-[var(--color-on-surface-dim)]">No active agent rows are available.</td></tr></tbody></table></div>
                                </article>
                            </div>
                        </template>
                    </section>
                </div>
            </section>

        </main>
    </div>

    <script>
        function operationsInsightsDashboard(options) {
            const today = new Date();
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            const start = new Date(today);
            start.setDate(start.getDate() - 6);

            return {
                section: 'dashboard',
                mode: 'historical',
                campaign: options.selectedCampaign,
                queryDate: formatDate(start),
                endDate: formatDate(today),
                salesDate: formatDate(today),
                salesStart: '06:00',
                salesEnd: '18:00',
                data: null,
                loading: false,
                error: null,
                lastUpdated: null,
                pollTimer: null,

                get dashboard() { return this.data?.dashboard || {}; },
                get reports() { return this.data?.reports || {}; },

                init() {
                    this.refreshAll();
                    this.$watch('section', () => this.renderChartsSoon());
                },

                cleanup() {
                    if (this.pollTimer) clearInterval(this.pollTimer);
                    window.crmCharts?.destroyGroup?.('operations-insights');
                },

                setMode(mode) {
                    if (this.mode === mode) return;
                    this.mode = mode;
                    this.refreshAll();
                },

                restartPolling() {
                    if (this.pollTimer) clearInterval(this.pollTimer);
                    this.pollTimer = null;
                    if (this.mode === 'live' || this.mode === 'today') {
                        this.pollTimer = setInterval(() => this.refreshAll(true), 20000);
                    }
                },

                async refreshAll(silent = false) {
                    if (this.loading) return;
                    if (!silent) this.loading = true;
                    this.error = null;

                    const params = new URLSearchParams({
                        campaign: this.campaign,
                        mode: this.mode,
                        sales_date: this.salesDate,
                        sales_start: this.salesStart,
                        sales_end: this.salesEnd,
                    });
                    if (this.mode === 'historical') {
                        params.set('query_date', this.queryDate);
                        params.set('end_date', this.endDate);
                        params.set('comparison', 'previous_period');
                    }

                    try {
                        const response = await fetch(`/api/operations-insights?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const payload = await response.json();
                        if (!response.ok || payload?.success !== true) {
                            throw new Error(payload?.message || `Request failed with status ${response.status}`);
                        }
                        this.data = payload.data;
                        this.lastUpdated = new Date(payload.data?.context?.generated_at || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        this.restartPolling();
                        await this.$nextTick();
                        this.renderCharts();
                    } catch (error) {
                        this.error = error?.message || 'Report data could not be loaded.';
                    } finally {
                        this.loading = false;
                    }
                },

                renderChartsSoon() {
                    this.$nextTick(() => this.renderCharts());
                },

                sectionVisible(name) {
                    const section = this.dashboard?.layout?.sections?.[name];
                    return section ? section.visible !== false : true;
                },

                amountVisible(key = null) {
                    const amounts = this.dashboard?.layout?.amounts || {};
                    if (amounts.enabled === false) return false;
                    return key ? amounts[key] !== false : true;
                },

                async renderCharts() {
                    if (!this.data || !window.ApexChartsLoader) return;
                    window.crmCharts?.destroyGroup?.('operations-insights');
                    const ApexCharts = await window.ApexChartsLoader();
                    const textColor = getComputedStyle(document.documentElement).getPropertyValue('--color-on-surface-muted').trim();
                    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim();
                    const primary = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim();

                    const render = (key, element, options) => {
                        if (!element) return;
                        const chart = new ApexCharts(element, options);
                        window.crmCharts?.register?.('operations-insights', key, chart);
                        chart.render();
                    };

                    if (this.section === 'dashboard') {
                        if (!this.sectionVisible('activity')) return;
                        const trend = this.dashboard?.activity?.last_24_hours || {};
                        render('activity', document.getElementById('operations-activity-chart'), {
                            chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: false } },
                            series: [{ name: 'Activity', data: trend.values || [] }],
                            xaxis: { categories: trend.labels || [], labels: { style: { colors: textColor, fontSize: '10px' }, rotate: -35 } },
                            yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: textColor } } },
                            grid: { borderColor },
                            stroke: { curve: 'smooth', width: 2 },
                            fill: { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .03 } },
                            colors: [primary],
                            dataLabels: { enabled: false },
                            tooltip: { theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light' },
                        });
                        return;
                    }

                    if (this.mode === 'historical') {
                        const volume = this.reports?.call_volume || {};
                        render('call-volume', document.getElementById('operations-report-call-chart'), {
                            chart: { type: 'bar', height: 280, toolbar: { show: false }, animations: { enabled: false } },
                            series: [{ name: 'Calls', data: volume.values || [] }],
                            xaxis: { categories: volume.labels || [], labels: { style: { colors: textColor, fontSize: '10px' }, rotate: -35 } },
                            yaxis: { min: 0, labels: { style: { colors: textColor } } },
                            grid: { borderColor }, colors: [primary], dataLabels: { enabled: false },
                        });

                        const dispositions = this.reports?.dispositions || {};
                        render('dispositions', document.getElementById('operations-report-disposition-chart'), {
                            chart: { type: 'bar', height: 280, toolbar: { show: false }, animations: { enabled: false } },
                            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                            series: [{ name: 'Calls', data: dispositions.values || [] }],
                            xaxis: { categories: dispositions.labels || [], labels: { style: { colors: textColor } } },
                            yaxis: { labels: { style: { colors: textColor } } },
                            grid: { borderColor }, colors: [primary], dataLabels: { enabled: false },
                        });
                    }
                },

                number(value) {
                    return value === null || value === undefined || value === '' ? '—' : Number(value).toLocaleString();
                },
                decimal(value) {
                    return value === null || value === undefined || value === '' ? '—' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
                },
                percent(value) {
                    return value === null || value === undefined || value === '' ? '—' : `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}%`;
                },
                money(value) {
                    return value === null || value === undefined || value === '' ? '—' : new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(Number(value));
                },
                duration(value) {
                    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '—';
                    const seconds = Math.max(0, Math.round(Number(value)));
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = seconds % 60;
                    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}:${String(secs).padStart(2, '0')}`;
                },
                humanize(value) {
                    return String(value || '—').replace(/[_-]+/g, ' ').replace(/\b\w/g, character => character.toUpperCase());
                },
                agentName(row) {
                    return row?.full_name || row?.name || row?.user || row?.agent || '—';
                },
                salesWindowLabel() {
                    return `${this.salesDate} · ${this.salesStart}–${this.salesEnd}`;
                },
            };
        }
    </script>
</body>
</html>
