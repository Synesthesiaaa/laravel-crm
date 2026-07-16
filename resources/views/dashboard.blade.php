@extends('layouts.app')

@section('title', 'Dashboard - ' . ($campaignName ?? 'CRM'))
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Dashboard')

@section('content')
@php
    $monthTitle = now()->format('F Y');
    $salesRangeLabel = $salesFilter['from']->format('M j, Y').' · '.$salesFilter['from']->format('g:i A').'–'.$salesFilter['until']->format('g:i A');
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

    <div x-data="{
        salesModalCloseTimer: null,
        leaderboardModalCloseTimer: null,
        openSalesModal() {
            this.cancelSalesModalClose();
            $store.modal.show('sales-summary');
        },
        scheduleSalesModalClose() {
            this.cancelSalesModalClose();
            this.salesModalCloseTimer = window.setTimeout(() => {
                if ($store.modal.is('sales-summary')) {
                    $store.modal.hide();
                }
                this.salesModalCloseTimer = null;
            }, 300);
        },
        cancelSalesModalClose() {
            if (this.salesModalCloseTimer !== null) {
                window.clearTimeout(this.salesModalCloseTimer);
                this.salesModalCloseTimer = null;
            }
        },
        openLeaderboardModal() {
            this.cancelLeaderboardModalClose();
            $store.modal.show('agent-leaderboard');
        },
        scheduleLeaderboardModalClose() {
            this.cancelLeaderboardModalClose();
            this.leaderboardModalCloseTimer = window.setTimeout(() => {
                if ($store.modal.is('agent-leaderboard')) {
                    $store.modal.hide();
                }
                this.leaderboardModalCloseTimer = null;
            }, 300);
        },
        cancelLeaderboardModalClose() {
            if (this.leaderboardModalCloseTimer !== null) {
                window.clearTimeout(this.leaderboardModalCloseTimer);
                this.leaderboardModalCloseTimer = null;
            }
        },
    }">
        {{-- KPI + context stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-stagger">
            <div tabindex="0"
                 class="rounded-xl focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                 x-on:mouseenter="openSalesModal()"
                 x-on:mouseleave="scheduleSalesModalClose()"
                 x-on:click="openSalesModal()"
                 x-on:focusin="openSalesModal()">
                <x-stat-card label="Sales" :value="number_format($kpis['sales'] ?? 0)" :secondary="$salesRangeLabel.' · Total value: '.number_format($kpis['sales_amount'] ?? 0, 2)" icon="check-circle" color="success" />
            </div>
            <div tabindex="0"
                 class="rounded-xl focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                 x-on:mouseenter="openLeaderboardModal()"
                 x-on:mouseleave="scheduleLeaderboardModalClose()"
                 x-on:click="openLeaderboardModal()"
                 x-on:focusin="openLeaderboardModal()">
                <x-stat-card class="h-full" label="Top agent" :value="$kpis['top_agent'] ?? '—'" :secondary="($kpis['top_agent_sales'] ?? 0) > 0 ? number_format($kpis['top_agent_sales']).' sales · Total value: '.number_format($kpis['top_agent_sales_amount'] ?? 0, 2) : null" icon="user" color="warning" />
            </div>
            <x-stat-card label="Active Forms" :value="count($forms ?? [])" icon="document-text" color="info" />
            <x-stat-card label="Campaign" :value="strtoupper($campaign ?? '—')" icon="building-office" color="info" />
        </div>

        <x-modal name="sales-summary"
                 title="Sales by form"
                 maxWidth="lg"
                 :pointer-through-backdrop="true"
                 x-on:mouseenter="cancelSalesModalClose()"
                 x-on:mouseleave="scheduleSalesModalClose()">
            <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="text-sm font-medium text-[var(--color-on-surface)]">
                    Date
                    <input type="date" name="sales_date" value="{{ $salesFilter['date'] }}" class="form-input mt-1 w-full">
                </label>
                <label class="text-sm font-medium text-[var(--color-on-surface)]">
                    Start time
                    <input type="time" name="sales_start" value="{{ $salesFilter['start'] }}" class="form-input mt-1 w-full">
                </label>
                <label class="text-sm font-medium text-[var(--color-on-surface)]">
                    End time
                    <input type="time" name="sales_end" value="{{ $salesFilter['end'] }}" class="form-input mt-1 w-full">
                </label>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="btn-primary">Apply range</button>
                </div>
            </form>

            <p class="mt-5 text-sm text-[var(--color-on-surface-muted)]">{{ $salesRangeLabel }}. Amounts come only from numeric form fields marked as sale amounts.</p>

            <div class="md-table-wrap mt-4">
                @if(!empty($kpis['sales_by_form']))
                    <table>
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th class="text-right">Sales</th>
                                <th class="text-right">Total amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kpis['sales_by_form'] as $formSale)
                                <tr>
                                    <td class="font-medium text-[var(--color-on-surface)]">{{ $formSale['form_name'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($formSale['sales']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($formSale['sales_amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No marked form sale fields are available for this campaign.</p>
                @endif
            </div>
        </x-modal>

        <x-modal name="agent-leaderboard"
                 title="Daily agent leaderboard"
                 maxWidth="lg"
                 :pointer-through-backdrop="true"
                 x-on:mouseenter="cancelLeaderboardModalClose()"
                 x-on:mouseleave="scheduleLeaderboardModalClose()">
            <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="text-sm font-medium text-[var(--color-on-surface)]">
                    Date
                    <input type="date" name="sales_date" value="{{ $salesFilter['date'] }}" class="form-input mt-1 w-full">
                </label>
                <label class="text-sm font-medium text-[var(--color-on-surface)]">
                    Start time
                    <input type="time" name="sales_start" value="{{ $salesFilter['start'] }}" class="form-input mt-1 w-full">
                </label>
                <label class="text-sm font-medium text-[var(--color-on-surface)]">
                    End time
                    <input type="time" name="sales_end" value="{{ $salesFilter['end'] }}" class="form-input mt-1 w-full">
                </label>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="btn-primary">Apply range</button>
                </div>
            </form>

            <p class="mt-5 text-sm text-[var(--color-on-surface-muted)]">{{ $salesRangeLabel }}. Ranked by qualifying form sales, then total marked-form sale amount.</p>

            <div class="md-table-wrap mt-4">
                @if(!empty($agentLeaderboard))
                    <table>
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Agent</th>
                                <th class="text-right">Sales</th>
                                <th class="text-right">Sale amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($agentLeaderboard as $idx => $row)
                                <tr>
                                    <td class="text-[var(--color-on-surface-dim)]">{{ $idx + 1 }}</td>
                                    <td class="font-medium text-[var(--color-on-surface)]">{{ $row['agent'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['sales_count']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['sales_amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No qualifying form sales are available for this range.</p>
                @endif
            </div>
        </x-modal>
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

    {{-- Daily campaign leaderboard --}}
    <div class="md-card overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Agent leaderboard — {{ $salesRangeLabel }}</h3>
            <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">Ranked by qualifying form sales, then total marked-form sale amount.</p>
        </div>
        <div class="md-table-wrap">
            @if(!empty($agentLeaderboard))
                <table>
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Agent</th>
                            <th class="text-right">Sales</th>
                            <th class="text-right">Sale amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agentLeaderboard as $idx => $row)
                            <tr>
                                <td class="text-[var(--color-on-surface-dim)]">{{ $idx + 1 }}</td>
                                <td class="font-medium text-[var(--color-on-surface)]">{{ $row['agent'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['sales_count']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['sales_amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No qualifying form sales are available for this range.</p>
            @endif
        </div>
    </div>

    {{-- Daily and month-to-date campaign report --}}
    @php
        $report = $dailyCampaignReport ?? [];
        $reportForms = $report['forms'] ?? [];
        $reportTables = [
            [
                'title' => 'Daily amounts',
                'subtitle' => 'Submitted amounts by form for '.($report['date'] ?? now()->toDateString()),
                'rows' => $report['daily'] ?? [],
                'totals' => $report['totals']['daily'] ?? ['counts' => [], 'amounts' => [], 'total_count' => 0, 'total_amount' => 0],
                'mode' => 'amounts',
            ],
            [
                'title' => 'Daily counts',
                'subtitle' => 'Accounts submitted by form for '.($report['date'] ?? now()->toDateString()),
                'rows' => $report['daily'] ?? [],
                'totals' => $report['totals']['daily'] ?? ['counts' => [], 'amounts' => [], 'total_count' => 0, 'total_amount' => 0],
                'mode' => 'counts',
            ],
            [
                'title' => 'Month to date accounts',
                'subtitle' => 'Accounts submitted since '.now()->startOfMonth()->format('M j, Y'),
                'rows' => $report['month_to_date'] ?? [],
                'totals' => $report['totals']['month_to_date'] ?? ['counts' => [], 'amounts' => [], 'total_count' => 0, 'total_amount' => 0],
                'mode' => 'account-summary',
            ],
            [
                'title' => 'Month to date submitted amounts',
                'subtitle' => 'Submitted amounts by form since '.now()->startOfMonth()->format('M j, Y'),
                'rows' => $report['month_to_date'] ?? [],
                'totals' => $report['totals']['month_to_date'] ?? ['counts' => [], 'amounts' => [], 'total_count' => 0, 'total_amount' => 0],
                'mode' => 'amounts',
            ],
        ];
    @endphp
    <div class="space-y-4" aria-label="Campaign daily and month-to-date report">
        <div class="flex items-end justify-between gap-3 flex-wrap">
            <div>
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Campaign report</h3>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">{{ $campaignName }} · live totals for the selected campaign</p>
            </div>
            @if($reportForms !== [])
                <span class="text-xs text-[var(--color-on-surface-dim)]">{{ count($reportForms) }} form{{ count($reportForms) === 1 ? '' : 's' }}</span>
            @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 animate-stagger">
            @foreach($reportTables as $reportTable)
                <section class="md-card md-card--static overflow-hidden" data-report-table="{{ Illuminate\Support\Str::slug($reportTable['title']) }}">
                    <div class="px-5 py-4 border-b border-[var(--color-border)]">
                        <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">{{ $reportTable['title'] }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">{{ $reportTable['subtitle'] }}</p>
                    </div>
                    <div class="table-scroll-wrap">
                        @if($reportTable['rows'] !== [])
                            <div class="md-table-wrap border-0 rounded-none shadow-none">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Agent name</th>
                                            @if($reportTable['mode'] === 'account-summary')
                                                <th class="text-right">Total accounts</th>
                                                <th class="text-right">Submitted amount</th>
                                            @else
                                                @foreach($reportForms as $form)
                                                    <th class="text-right">{{ $form['name'] }}</th>
                                                @endforeach
                                                <th class="text-right">{{ $reportTable['mode'] === 'counts' ? 'Total accounts' : 'Total' }}</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reportTable['rows'] as $row)
                                            <tr>
                                                <td class="font-medium text-[var(--color-on-surface)] whitespace-nowrap">{{ $row['agent'] }}</td>
                                                @if($reportTable['mode'] === 'account-summary')
                                                    <td class="text-right tabular-nums">{{ number_format($row['total_count']) }}</td>
                                                    <td class="text-right tabular-nums">{{ $row['total_amount'] > 0 ? number_format($row['total_amount'], 2) : '—' }}</td>
                                                @else
                                                    @foreach($reportForms as $form)
                                                        @if($reportTable['mode'] === 'counts')
                                                            <td class="text-right tabular-nums">{{ number_format($row['counts'][$form['code']] ?? 0) }}</td>
                                                        @else
                                                            <td class="text-right tabular-nums">{{ ($row['amounts'][$form['code']] ?? 0) > 0 ? number_format($row['amounts'][$form['code']], 2) : '—' }}</td>
                                                        @endif
                                                    @endforeach
                                                    @if($reportTable['mode'] === 'counts')
                                                        <td class="text-right font-semibold tabular-nums">{{ number_format($row['total_count']) }}</td>
                                                    @else
                                                        <td class="text-right font-semibold tabular-nums">{{ $row['total_amount'] > 0 ? number_format($row['total_amount'], 2) : '—' }}</td>
                                                    @endif
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-[var(--color-primary-muted)] font-semibold">
                                            <td>Total</td>
                                            @if($reportTable['mode'] === 'account-summary')
                                                <td class="text-right tabular-nums">{{ number_format($reportTable['totals']['total_count']) }}</td>
                                                <td class="text-right tabular-nums">{{ $reportTable['totals']['total_amount'] > 0 ? number_format($reportTable['totals']['total_amount'], 2) : '—' }}</td>
                                            @else
                                                @foreach($reportForms as $form)
                                                    @if($reportTable['mode'] === 'counts')
                                                        <td class="text-right tabular-nums">{{ number_format($reportTable['totals']['counts'][$form['code']] ?? 0) }}</td>
                                                    @else
                                                        <td class="text-right tabular-nums">{{ ($reportTable['totals']['amounts'][$form['code']] ?? 0) > 0 ? number_format($reportTable['totals']['amounts'][$form['code']], 2) : '—' }}</td>
                                                    @endif
                                                @endforeach
                                                <td class="text-right tabular-nums">{{ $reportTable['mode'] === 'counts' ? number_format($reportTable['totals']['total_count']) : ($reportTable['totals']['total_amount'] > 0 ? number_format($reportTable['totals']['total_amount'], 2) : '—') }}</td>
                                            @endif
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <p class="table-empty py-10 text-center text-sm">No submissions for this period.</p>
                        @endif
                    </div>
                </section>
            @endforeach
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
    const scope = window.crmSoftNav?.currentScope?.() || window.location.pathname;
    const chartGroup = 'dashboard';
    const campaignCode = @json($campaign ?? '');
    const fallbackIntervalMs = 30_000;
    let dashboardTeardown = null;
    let fallbackTimer = null;
    let refreshTimer = null;
    let refreshInFlight = false;
    let echo = null;
    let echoReadyHandler = null;

    function destroyCharts() {
        window.crmCharts?.destroyGroup?.(chartGroup);
    }

    function scheduleRefresh() {
        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(() => {
            if (refreshInFlight || typeof window.crmSoftNav?.refresh !== 'function') {
                return;
            }

            refreshInFlight = true;
            Promise.resolve(window.crmSoftNav.refresh()).finally(() => {
                refreshInFlight = false;
            });
        }, 350);
    }

    function teardownLiveUpdates() {
        window.clearTimeout(refreshTimer);
        window.clearInterval(fallbackTimer);
        refreshTimer = null;
        fallbackTimer = null;
        if (echoReadyHandler) {
            window.removeEventListener('telephony-echo:ready', echoReadyHandler);
            echoReadyHandler = null;
        }
        dashboardTeardown?.();
        dashboardTeardown = null;
    }

    function startLiveUpdates() {
        const initializeEcho = () => {
            echo = window.TelephonyEcho;
            if (!echo?.isBroadcastEnabled?.()) {
                return;
            }

            echo.initEcho?.();
            dashboardTeardown = echo.subscribeDashboardChannel?.(campaignCode, scheduleRefresh) || null;
        };

        if (window.TelephonyEcho) {
            initializeEcho();
        } else {
            echoReadyHandler = initializeEcho;
            window.addEventListener('telephony-echo:ready', echoReadyHandler, { once: true });
        }

        fallbackTimer = window.setInterval(() => {
            if (!(echo || window.TelephonyEcho)?.isEchoConnected?.()) {
                scheduleRefresh();
            }
        }, fallbackIntervalMs);
    }

    async function mountAreaChart(ApexCharts, elId, categories, values, config) {
        const el = document.getElementById(elId);
        if (!el || !document.getElementById('main-layout')?.contains(el)) {
            return;
        }
        if (!Array.isArray(categories) || categories.length === 0) {
            return;
        }

        el.innerHTML = '';
        const chart = new ApexCharts(el, {
            series: [{ name: 'Submissions', data: values }],
            chart: {
                type: 'area',
                height: 240,
                width: '100%',
                toolbar: { show: false },
                background: 'transparent',
                fontFamily: 'DM Sans, ui-sans-serif',
                animations: { enabled: true, easing: 'easeinout', speed: 600 },
            },
            colors: ['#e91e8c'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories,
                labels: { style: { colors: config.textColor, fontSize: '11px' }, rotate: -30 },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: config.textColor, fontSize: '11px' } }, min: 0 },
            grid: { borderColor: config.gridColor, strokeDashArray: 3 },
            tooltip: { theme: config.isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            theme: { mode: config.isDark ? 'dark' : 'light' },
        });

        window.crmCharts?.register?.(chartGroup, elId, chart);
        await chart.render();

        try {
            chart.resize();
        } catch (_) {}
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

        const main = document.getElementById('main-layout');
        if (!main || !main.querySelector('#chart-monthly-activity')) {
            return;
        }

        const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
        const config = {
            isDark,
            textColor: isDark ? '#a1a1aa' : '#52525b',
            gridColor: isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)',
        };

        await Promise.all([
            mountAreaChart(ApexCharts, 'chart-daily-activity', @json($dailyActivity['labels'] ?? []), @json($dailyActivity['values'] ?? []), config),
            mountAreaChart(ApexCharts, 'chart-weekly-activity', @json($weeklyActivity['labels'] ?? []), @json($weeklyActivity['values'] ?? []), config),
            mountAreaChart(ApexCharts, 'chart-monthly-activity', @json($monthlyActivity['labels'] ?? []), @json($monthlyActivity['values'] ?? []), config),
        ]);

        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        window.resizeCrmDashboardCharts?.();
        requestAnimationFrame(() => window.resizeCrmDashboardCharts?.());
        setTimeout(() => window.resizeCrmDashboardCharts?.(), 120);
        setTimeout(() => window.resizeCrmDashboardCharts?.(), 360);
    }

    window.crmSoftNav?.register?.(scope, {
        beforeSwap: () => {
            teardownLiveUpdates();
            destroyCharts();
        },
        afterSwap: () => {
            void renderCharts();
        },
    });

    startLiveUpdates();

    if (!window.crmSoftNav?.isRehydrating?.()) {
        await renderCharts();
    }
})();
</script>
@endpush
