@extends('layouts.app')

@section('title', 'Dashboard - ' . ($campaignName ?? 'CRM'))
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Dashboard')

@section('content')
@php
    $monthTitle = now()->format('F Y');
    $amountsEnabled = (bool) data_get($dashboardLayout, 'amounts.enabled', true);
    $amountVisible = static fn (string $key): bool => $amountsEnabled && (bool) data_get($dashboardLayout, 'amounts.'.$key, true);
    $dashboardSections = $dashboardLayout['sections'] ?? [];
    $sectionVisible = static fn (string $key): bool => ($dashboardSections[$key]['visible'] ?? true) === true;
    $sectionOrder = static fn (string $key): int => (int) ($dashboardSections[$key]['order'] ?? 0);
    $leaderboardTotalSales = collect($agentLeaderboard ?? [])->sum('sales_count');
    $leaderboardTotalAmount = collect($agentLeaderboard ?? [])->sum('sales_amount');
    $salesRangeLabel = $salesFilter['from']->format('M j, Y').' · '.$salesFilter['from']->format('g:i A').'–'.$salesFilter['until']->format('g:i A');
    $salesMode = ($salesMode ?? 'legacy') === 'custom' ? 'custom' : 'legacy';
    $salesAttributionLabel = $salesMode === 'custom'
        ? 'Amounts follow this campaign’s custom tag rules.'
        : 'Amounts come only from numeric form fields marked as sale amounts.';
    $summary = $dashboardSummary ?? [];
    $summaryCurrent = data_get($summary, 'summary.current', ['count' => 0, 'amount' => 0.0]);
    $summaryPrevious = data_get($summary, 'summary.previous', ['count' => 0, 'amount' => 0.0]);
    $summaryCountComparison = data_get($summary, 'comparison.count', []);
    $summaryAmountComparison = data_get($summary, 'comparison.amount', []);
    $summaryDaily = data_get($summary, 'daily', []);
    $summaryHasActivity = (bool) data_get($summary, 'has_activity', false);
    $summaryCurrencySymbol = (string) data_get($summary, 'currency.symbol', config('dashboard.currency_symbol', '₱'));
    $summaryModeLabel = data_get($summary, 'period.mode') === 'completed_month' ? 'Completed month' : 'Month to date';
    $summaryCurrentPeriodLabel = (string) data_get($summary, 'period.current.label', $monthTitle);
    $summaryPreviousPeriodLabel = (string) data_get($summary, 'period.previous.label', 'Previous month');
    $formatSummaryNumber = static function (float|int $amount, bool $compact = false): string {
        $amount = abs((float) $amount);
        if (! $compact || $amount < 1000) {
            return number_format($amount, 2);
        }

        foreach ([
            1000000000 => 'B',
            1000000 => 'M',
            1000 => 'K',
        ] as $threshold => $suffix) {
            if ($amount >= $threshold) {
                return rtrim(rtrim(number_format($amount / $threshold, 2, '.', ''), '0'), '.').$suffix;
            }
        }

        return number_format($amount, 2);
    };
    $formatSummaryAmount = static function (float|int $amount, bool $compact = false) use ($summaryCurrencySymbol, $formatSummaryNumber): string {
        return ($amount < 0 ? '-' : '').$summaryCurrencySymbol.$formatSummaryNumber($amount, $compact);
    };
    $formatSignedAmount = static function (float|int $amount, bool $compact = false) use ($formatSummaryAmount): string {
        return ($amount > 0 ? '+' : ($amount < 0 ? '-' : '')).$formatSummaryAmount(abs((float) $amount), $compact);
    };
    $formatSignedCount = static fn (float|int $count): string => ($count > 0 ? '+' : ($count < 0 ? '-' : '')).number_format(abs((float) $count));
@endphp
<div class="space-y-8 flex flex-col">

    {{-- Welcome hero --}}
    @if($sectionVisible('welcome'))
    <section data-dashboard-section="welcome" style="order: {{ $sectionOrder('welcome') }}">
    <div class="md-hero">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-[var(--color-on-surface)]">Welcome to {{ data_get($branding, 'name', 'CRM') }}</h2>
                <p class="text-sm text-[var(--color-on-surface-muted)] mt-1">Hello, {{ $user->full_name ?? $user->username }}</p>
                <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">
                    Campaign: <span class="font-semibold text-[var(--color-primary)]">{{ $campaignName }}</span>
                </p>
            </div>
            <x-badge type="active">Online</x-badge>
        </div>
    </div>
    </section>
    @endif

    @if($sectionVisible('kpis'))
    <section data-dashboard-section="kpis" style="order: {{ $sectionOrder('kpis') }}">
    <div x-data="{}">
        {{-- KPI + context stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-stagger">
            <button type="button" class="text-left cursor-pointer rounded-xl focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]" aria-haspopup="dialog" x-on:click="$store.modal.show('sales-summary')">
                <x-stat-card label="Sales" :value="number_format($kpis['sales'] ?? 0)" :secondary="$salesRangeLabel.($amountsEnabled ? ' · Total value: '.number_format($kpis['sales_amount'] ?? 0, 2) : '')" icon="check-circle" color="success" />
            </button>
            <button type="button" class="text-left cursor-pointer rounded-xl focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]" aria-haspopup="dialog" x-on:click="$store.modal.show('agent-leaderboard')">
                <x-stat-card class="h-full" label="Top agent" :value="$kpis['top_agent'] ?? '—'" :secondary="($kpis['top_agent_sales'] ?? 0) > 0 ? number_format($kpis['top_agent_sales']).' sales'.($amountsEnabled ? ' · Total value: '.number_format($kpis['top_agent_sales_amount'] ?? 0, 2) : '') : null" icon="user" color="warning" />
            </button>
            <x-stat-card label="Active Forms" :value="count($forms ?? [])" icon="document-text" color="info" />
            <x-stat-card label="Campaign" :value="strtoupper($campaign ?? '—')" icon="building-office" color="info" />
        </div>

        {{-- Month comparison summary --}}
        <section class="mt-6 space-y-4" data-dashboard-summary aria-labelledby="dashboard-summary-title">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h3 id="dashboard-summary-title" class="text-sm font-semibold text-[var(--color-on-surface)]">Monthly performance</h3>
                    <p class="text-xs text-[var(--color-on-surface-muted)] mt-1">
                        {{ $summaryModeLabel }}: {{ $summaryCurrentPeriodLabel }}
                    </p>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">
                        Compared with {{ $summaryPreviousPeriodLabel }}
                    </p>
                </div>
                <span class="badge badge-info">{{ $summaryModeLabel }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 {{ $amountVisible('total') && $amountVisible('change') ? 'xl:grid-cols-4' : ($amountVisible('total') || $amountVisible('change') ? 'xl:grid-cols-3' : '') }} gap-4 animate-stagger">
                <x-stat-card
                    label="Transactions"
                    :value="number_format($summaryCurrent['count'])"
                    :secondary="'Current period: '.$summaryCurrentPeriodLabel"
                    icon="clipboard-document-check"
                    color="success"
                    :trend="$summaryCountComparison['percentage'] ?? null"
                    :trend-difference="$formatSignedCount($summaryCountComparison['difference'] ?? 0)"
                    :trend-label="$summaryCountComparison['message'] ?? 'vs last month'"
                    :trend-status="$summaryCountComparison['status'] ?? 'unchanged'" />
                @if($amountVisible('total'))
                <x-stat-card
                    label="Total amount"
                    :value="$formatSummaryAmount($summaryCurrent['amount'] ?? 0, true)"
                    :secondary="'Current period: '.$summaryCurrentPeriodLabel"
                    icon="tag"
                    color="primary"
                    :trend="$summaryAmountComparison['percentage'] ?? null"
                    :trend-difference="$formatSignedAmount($summaryAmountComparison['difference'] ?? 0, true)"
                    :trend-label="$summaryAmountComparison['message'] ?? 'vs last month'"
                    :trend-status="$summaryAmountComparison['status'] ?? 'unchanged'" />
                @endif
                <x-stat-card
                    label="Transaction change"
                    :value="$formatSignedCount($summaryCountComparison['difference'] ?? 0)"
                    :secondary="number_format($summaryCurrent['count']).' current vs '.number_format($summaryPrevious['count']).' previous'"
                    icon="chart-bar"
                    color="info"
                    :trend="$summaryCountComparison['percentage'] ?? null"
                    :trend-label="$summaryCountComparison['message'] ?? 'vs last month'"
                    :trend-status="$summaryCountComparison['status'] ?? 'unchanged'" />
                @if($amountVisible('change'))
                <x-stat-card
                    label="Amount change"
                    :value="$formatSignedAmount($summaryAmountComparison['difference'] ?? 0, true)"
                    :secondary="$formatSummaryAmount($summaryCurrent['amount'] ?? 0, true).' current vs '.$formatSummaryAmount($summaryPrevious['amount'] ?? 0, true).' previous'"
                    icon="arrow-trending-up"
                    color="warning"
                    :trend="$summaryAmountComparison['percentage'] ?? null"
                    :trend-label="$summaryAmountComparison['message'] ?? 'vs last month'"
                    :trend-status="$summaryAmountComparison['status'] ?? 'unchanged'" />
                @endif
            </div>

            <div class="chart-container" data-dashboard-summary-chart>
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h4 class="chart-title mb-1">Daily comparison</h4>
                        <p class="text-xs text-[var(--color-on-surface-muted)]">Current period versus the equivalent previous-month days.</p>
                        @if($amountsEnabled)
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">{{ data_get($summary, 'amount_definition', 'Amount attribution follows the campaign configuration.') }}</p>
                        @endif
                    </div>
                    @if($summaryHasActivity && $amountVisible('charts'))
                        <div x-data="{ mode: 'volume' }" x-init="$watch('mode', value => window.setDashboardSummaryMode?.(value))" class="inline-flex rounded-lg border border-[var(--color-border)] p-1" role="group" aria-label="Chart measure">
                            <button type="button" class="min-h-11 rounded-md px-3 text-xs font-semibold transition-colors" :class="mode === 'volume' ? 'bg-[var(--color-primary-muted)] text-[var(--color-primary)]' : 'text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'" :aria-pressed="mode === 'volume'" x-on:click="mode = 'volume'">Volume</button>
                            <button type="button" class="min-h-11 rounded-md px-3 text-xs font-semibold transition-colors" :class="mode === 'amount' ? 'bg-[var(--color-primary-muted)] text-[var(--color-primary)]' : 'text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'" :aria-pressed="mode === 'amount'" x-on:click="mode = 'amount'">Amount</button>
                        </div>
                    @endif
                </div>

                @if($summaryHasActivity)
                    <div id="chart-dashboard-summary" class="mt-4 min-h-[280px]" role="img" aria-describedby="dashboard-summary-description" aria-busy="true">
                        <div class="skeleton h-[280px] w-full" data-summary-chart-loading aria-hidden="true"></div>
                    </div>
                    <p id="dashboard-summary-description" class="sr-only">A line chart compares daily transaction {{ $amountVisible('charts') ? 'volume or amount' : 'volume' }} for the current period and the equivalent previous-month period. The previous period uses a dashed line.</p>
                    <p data-summary-chart-status class="mt-2 text-xs text-[var(--color-on-surface-dim)]" role="status" aria-live="polite">Loading comparison chart...</p>

                    <details class="mt-4 border-t border-[var(--color-border)] pt-3">
                        <summary class="cursor-pointer text-xs font-semibold text-[var(--color-on-surface-muted)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">View daily summary data</summary>
                        <div class="md-table-wrap mt-3">
                            <table>
                                <caption class="sr-only">Daily current and previous period {{ $amountVisible('tables') ? 'transaction and amount' : 'transaction' }} comparison</caption>
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th class="text-right">Current volume</th>
                                        @if($amountVisible('tables'))
                                            <th class="text-right">Current amount</th>
                                        @endif
                                        <th class="text-right">Previous volume</th>
                                        @if($amountVisible('tables'))
                                            <th class="text-right">Previous amount</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summaryDaily as $summaryDay)
                                        <tr>
                                            <td class="font-medium text-[var(--color-on-surface)]" title="{{ $summaryDay['current_date'] }}">{{ $summaryDay['label'] }}</td>
                                            <td class="text-right tabular-nums">{{ number_format($summaryDay['current']['count']) }}</td>
                                            @if($amountVisible('tables'))
                                                <td class="text-right tabular-nums">{{ $formatSummaryAmount($summaryDay['current']['amount']) }}</td>
                                            @endif
                                            <td class="text-right tabular-nums" title="{{ $summaryDay['previous_date'] ?? 'No equivalent date' }}">{{ $summaryDay['previous']['count'] === null ? '—' : number_format($summaryDay['previous']['count']) }}</td>
                                            @if($amountVisible('tables'))
                                                <td class="text-right tabular-nums">{{ $summaryDay['previous']['amount'] === null ? '—' : $formatSummaryAmount($summaryDay['previous']['amount']) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @else
                    <div class="mt-4 rounded-lg border border-dashed border-[var(--color-border)] px-4 py-8 text-center" role="status">
                        <x-icon name="chart-bar" class="mx-auto h-8 w-8 text-[var(--color-on-surface-dim)]" />
                        <p class="mt-3 text-sm font-medium text-[var(--color-on-surface-muted)]">No activity found for the selected period.</p>
                        <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">The chart will appear when qualifying sales activity is recorded.</p>
                    </div>
                @endif
            </div>
        </section>

        <x-modal name="sales-summary"
                 title="Sales by form"
                 :close-on-backdrop="true"
                 maxWidth="lg"
                 x-on:keydown.escape.window="if ($store.modal.is('sales-summary') || $store.modal.is('agent-leaderboard')) $store.modal.hide()">
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

            <p class="mt-5 text-sm text-[var(--color-on-surface-muted)]">{{ $salesRangeLabel }}. @if($amountsEnabled) {{ $salesAttributionLabel }} @endif</p>

            <div class="md-table-wrap mt-4">
                @if(!empty($kpis['sales_by_form']))
                    <table>
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th class="text-right">Sales</th>
                                @if($amountVisible('tables'))
                                    <th class="text-right">Total amount</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kpis['sales_by_form'] as $formSale)
                                <tr>
                                    <td class="font-medium text-[var(--color-on-surface)]">{{ $formSale['form_name'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($formSale['sales']) }}</td>
                                    @if($amountVisible('tables'))
                                        <td class="text-right tabular-nums">{{ number_format($formSale['sales_amount'], 2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">{{ $salesMode === 'custom' ? 'No custom sales rules matched this range.' : 'No marked form sale fields are available for this campaign.' }}</p>
                @endif
            </div>
        </x-modal>

        <x-modal name="agent-leaderboard"
                 title="Daily agent leaderboard"
                 :close-on-backdrop="true"
                 maxWidth="lg"
                 x-on:keydown.escape.window="if ($store.modal.is('sales-summary') || $store.modal.is('agent-leaderboard')) $store.modal.hide()">
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

            <p class="mt-5 text-sm text-[var(--color-on-surface-muted)]">{{ $salesRangeLabel }}. {{ $amountsEnabled ? 'Ranked by total sale amount, then qualifying sales count and agent name.' : 'Ranked using campaign sales rules.' }}</p>

            <div class="md-table-wrap mt-4">
                @if(!empty($agentLeaderboard))
                    <table>
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Agent</th>
                                <th class="text-right">Sales</th>
                                @if($amountVisible('tables'))
                                    <th class="text-right">Sale amount</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($agentLeaderboard as $idx => $row)
                                <tr>
                                    <td class="text-[var(--color-on-surface-dim)]">{{ $idx + 1 }}</td>
                                    <td class="font-medium text-[var(--color-on-surface)]">{{ $row['agent'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['sales_count']) }}</td>
                                    @if($amountVisible('tables'))
                                        <td class="text-right tabular-nums">{{ number_format($row['sales_amount'], 2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="font-semibold text-[var(--color-on-surface)]">Total</td>
                                <td class="text-right font-semibold tabular-nums">{{ number_format($leaderboardTotalSales) }}</td>
                                @if($amountVisible('tables'))
                                    <td class="text-right font-semibold tabular-nums">{{ number_format($leaderboardTotalAmount, 2) }}</td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No qualifying form sales are available for this range.</p>
                @endif
            </div>
        </x-modal>
    </div>
    </section>
    @endif

    {{-- Activity charts: daily / weekly / monthly --}}
    @if($sectionVisible('activity'))
    <section data-dashboard-section="activity" style="order: {{ $sectionOrder('activity') }}">
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
    </section>
    @endif

    {{-- Daily campaign leaderboard --}}
    @if($sectionVisible('leaderboard'))
    <section data-dashboard-section="leaderboard" style="order: {{ $sectionOrder('leaderboard') }}">
    <div class="md-card overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Agent leaderboard — {{ $salesRangeLabel }}</h3>
            <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">{{ $amountsEnabled ? 'Ranked by total sale amount, then qualifying sales count and agent name.' : 'Ranked using campaign sales rules.' }}</p>
        </div>
        <div class="md-table-wrap">
            @if(!empty($agentLeaderboard))
                <table>
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Agent</th>
                            <th class="text-right">Sales</th>
                            @if($amountVisible('tables'))
                                <th class="text-right">Sale amount</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agentLeaderboard as $idx => $row)
                            <tr>
                                <td class="text-[var(--color-on-surface-dim)]">{{ $idx + 1 }}</td>
                                <td class="font-medium text-[var(--color-on-surface)]">{{ $row['agent'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['sales_count']) }}</td>
                                @if($amountVisible('tables'))
                                    <td class="text-right tabular-nums">{{ number_format($row['sales_amount'], 2) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="font-semibold text-[var(--color-on-surface)]">Total</td>
                            <td class="text-right font-semibold tabular-nums">{{ number_format($leaderboardTotalSales) }}</td>
                            @if($amountVisible('tables'))
                                <td class="text-right font-semibold tabular-nums">{{ number_format($leaderboardTotalAmount, 2) }}</td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            @else
                <p class="table-empty py-8 text-center text-sm text-[var(--color-on-surface-dim)]">No qualifying form sales are available for this range.</p>
            @endif
        </div>
    </div>
    </section>
    @endif

    {{-- Daily and month-to-date campaign report --}}
    @if($sectionVisible('campaign_report'))
    <section data-dashboard-section="campaign_report" style="order: {{ $sectionOrder('campaign_report') }}">
    @php
        $report = $dailyCampaignReport ?? [];
        $reportForms = $report['forms'] ?? [];
        $reportTables = [
            [
                'title' => $salesMode === 'custom' ? 'Daily attributed amounts' : 'Daily amounts',
                'subtitle' => ($salesMode === 'custom' ? 'Attributed amounts by form for ' : 'Submitted amounts by form for ').($report['date'] ?? now()->toDateString()),
                'rows' => $report['daily'] ?? [],
                'totals' => $report['totals']['daily'] ?? ['counts' => [], 'amounts' => [], 'total_count' => 0, 'total_amount' => 0],
                'mode' => 'amounts',
            ],
            [
                'title' => $salesMode === 'custom' ? 'Month to date attributed amounts' : 'Month to date submitted amounts',
                'subtitle' => ($salesMode === 'custom' ? 'Attributed amounts by form since ' : 'Submitted amounts by form since ').now()->startOfMonth()->format('M j, Y'),
                'rows' => $report['month_to_date'] ?? [],
                'totals' => $report['totals']['month_to_date'] ?? ['counts' => [], 'amounts' => [], 'total_count' => 0, 'total_amount' => 0],
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
                'mode' => 'counts',
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

        <div class="grid gap-5 animate-stagger campaign-report-grid">
            @foreach($reportTables as $reportTable)
                @continue($reportTable['mode'] === 'amounts' && ! $amountVisible('tables'))
                <section class="md-card md-card--static overflow-hidden" data-report-table="{{ Illuminate\Support\Str::slug($reportTable['title']) }}">
                    <div class="px-5 py-4 border-b border-[var(--color-border)]">
                        <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">{{ $reportTable['title'] }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5">{{ $reportTable['subtitle'] }}</p>
                    </div>
                    <div class="table-scroll-wrap campaign-report-table-wrap">
                        @if($reportTable['rows'] !== [])
                            <div class="md-table-wrap border-0 rounded-none shadow-none">
                                <table class="report-table--wide">
                                    <thead>
                                        <tr>
                                            <th>Agent name</th>
                                            @foreach($reportForms as $form)
                                                <th class="text-right">{{ $form['name'] }}</th>
                                            @endforeach
                                            <th class="text-right">{{ $reportTable['mode'] === 'counts' ? 'Total accounts' : 'Total' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reportTable['rows'] as $row)
                                            <tr>
                                                <td class="font-medium text-[var(--color-on-surface)] whitespace-nowrap">{{ $row['agent'] }}</td>
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
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-[var(--color-primary-muted)] font-semibold">
                                            <td>Total</td>
                                            @foreach($reportForms as $form)
                                                @if($reportTable['mode'] === 'counts')
                                                    <td class="text-right tabular-nums">{{ number_format($reportTable['totals']['counts'][$form['code']] ?? 0) }}</td>
                                                @else
                                                    <td class="text-right tabular-nums">{{ ($reportTable['totals']['amounts'][$form['code']] ?? 0) > 0 ? number_format($reportTable['totals']['amounts'][$form['code']], 2) : '—' }}</td>
                                                @endif
                                            @endforeach
                                            <td class="text-right tabular-nums">{{ $reportTable['mode'] === 'counts' ? number_format($reportTable['totals']['total_count']) : ($reportTable['totals']['total_amount'] > 0 ? number_format($reportTable['totals']['total_amount'], 2) : '—') }}</td>
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
    </section>
    @endif

    {{-- Campaign forms --}}
    @if($sectionVisible('forms'))
    <section data-dashboard-section="forms" style="order: {{ $sectionOrder('forms') }}">
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
    </section>
    @endif

    {{-- Quick links --}}
    @if($sectionVisible('quick_links'))
    <section data-dashboard-section="quick_links" style="order: {{ $sectionOrder('quick_links') }}">
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
    </section>
    @endif

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
    let lastInteractionAt = 0;
    let liveUpdatesStopped = false;
    const markInteraction = () => { lastInteractionAt = Date.now(); };
    let echo = null;
    let echoReadyHandler = null;
    let summaryChart = null;
    let summaryChartConfig = null;
    let summaryMode = 'volume';
    const summaryDaily = @json($summaryDaily);
    const summaryCurrencySymbol = @json($summaryCurrencySymbol);
    const summaryCurrentLabel = @json($summaryCurrentPeriodLabel);
    const summaryPreviousLabel = @json($summaryPreviousPeriodLabel);

    function destroyCharts() {
        window.crmCharts?.destroyGroup?.(chartGroup);
        summaryChart = null;
        summaryChartConfig = null;
    }

    function shouldDeferRefresh() {
        return document.hidden
            || Boolean(window.Alpine?.store('modal')?.open)
            || Boolean(window.Alpine?.store('confirm')?.visible)
            || Boolean(document.activeElement?.matches('input, select, textarea, [contenteditable="true"]'))
            || Date.now() - lastInteractionAt < 1500;
    }

    function scheduleRefresh() {
        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(() => {
            if (liveUpdatesStopped || refreshInFlight || typeof window.crmSoftNav?.refresh !== 'function') {
                return;
            }

            if (shouldDeferRefresh()) {
                scheduleRefresh();
                return;
            }

            refreshInFlight = true;
            Promise.resolve(window.crmSoftNav.refresh({ shouldDefer: shouldDeferRefresh })).then((refreshed) => {
                if (refreshed === false && !liveUpdatesStopped) scheduleRefresh();
            }).catch(() => {
                // The next fallback or campaign event retries a failed refresh.
            }).finally(() => {
                refreshInFlight = false;
            });
        }, 350);
    }

    function teardownLiveUpdates() {
        liveUpdatesStopped = true;
        document.removeEventListener('scroll', markInteraction, true);
        document.removeEventListener('pointerdown', markInteraction, true);
        document.removeEventListener('keydown', markInteraction, true);
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
        document.addEventListener('scroll', markInteraction, { capture: true, passive: true });
        document.addEventListener('pointerdown', markInteraction, { capture: true, passive: true });
        document.addEventListener('keydown', markInteraction, true);
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

    function setSummaryChartStatus(message) {
        const status = document.querySelector('[data-summary-chart-status]');
        if (status) {
            status.textContent = message;
        }
    }

    function clearSummaryChartLoading() {
        document.querySelector('[data-summary-chart-loading]')?.remove();
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[character]);
    }

    function formatSummaryValue(value, mode = summaryMode) {
        const numericValue = Number(value) || 0;
        if (mode === 'amount') {
            return `${numericValue < 0 ? '-' : ''}${summaryCurrencySymbol}${Math.abs(numericValue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        return Math.round(numericValue).toLocaleString();
    }

    function formatSummaryAxisValue(value, mode = summaryMode) {
        const numericValue = Math.abs(Number(value) || 0);
        if (mode !== 'amount' || numericValue < 1000) {
            return formatSummaryValue(value, mode);
        }

        const units = [[1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'K']];
        const unit = units.find(([threshold]) => numericValue >= threshold);
        const scaled = numericValue / unit[0];
        return `${Number(value) < 0 ? '-' : ''}${summaryCurrencySymbol}${scaled.toFixed(1).replace(/\.0$/, '')}${unit[1]}`;
    }

    function formatSignedSummaryValue(value, mode = summaryMode) {
        const numericValue = Number(value) || 0;
        return `${numericValue > 0 ? '+' : numericValue < 0 ? '-' : ''}${formatSummaryValue(Math.abs(numericValue), mode)}`;
    }

    function summarySeries() {
        const key = summaryMode === 'amount' ? 'amount' : 'count';

        return [
            { name: summaryCurrentLabel, data: summaryDaily.map((point) => point.current[key]) },
            { name: summaryPreviousLabel, data: summaryDaily.map((point) => point.previous[key]) },
        ];
    }

    function summaryTooltip({ dataPointIndex }) {
        const point = summaryDaily[dataPointIndex];
        if (!point) {
            return '';
        }

        const key = summaryMode === 'amount' ? 'amount' : 'count';
        const currentValue = Number(point.current[key]) || 0;
        const hasPreviousEquivalent = point.previous_date !== null;
        const previousValue = hasPreviousEquivalent ? Number(point.previous[key]) || 0 : null;
        const difference = hasPreviousEquivalent ? currentValue - previousValue : null;
        const comparison = !hasPreviousEquivalent
            ? 'No equivalent date'
            : previousValue === 0
                ? (currentValue === 0 ? 'No change vs last month' : 'New activity vs last month')
                : `${difference >= 0 ? '+' : ''}${((difference / previousValue) * 100).toFixed(2)}% vs last month`;
        const currentDate = escapeHtml(point.current_date);
        const previousDate = escapeHtml(point.previous_date || 'No equivalent date');
        const currentLabel = escapeHtml(summaryCurrentLabel);
        const previousLabel = escapeHtml(summaryPreviousLabel);
        const previousDisplay = hasPreviousEquivalent ? formatSummaryValue(previousValue) : '—';
        const differenceDisplay = hasPreviousEquivalent ? formatSignedSummaryValue(difference) : '—';

        return `<div class="px-3 py-2 text-xs" style="background: var(--color-surface-card); color: var(--color-on-surface);">
            <div class="font-semibold">Day ${escapeHtml(point.label)}</div>
            <div class="mt-2 flex justify-between gap-6"><span>${currentLabel} <span class="text-[var(--color-on-surface-dim)]">(${currentDate})</span></span><strong>${formatSummaryValue(currentValue)}</strong></div>
            <div class="flex justify-between gap-6"><span>${previousLabel} <span class="text-[var(--color-on-surface-dim)]">(${previousDate})</span></span><strong>${previousDisplay}</strong></div>
            <div class="mt-2 border-t border-[var(--color-border)] pt-2"><span class="text-[var(--color-on-surface-dim)]">Difference</span> <strong>${differenceDisplay}</strong> <span class="text-[var(--color-on-surface-dim)]">${escapeHtml(comparison)}</span></div>
        </div>`;
    }

    function summaryChartOptions(config) {
        const amountMode = summaryMode === 'amount';
        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        return {
            series: summarySeries(),
            chart: {
                type: 'line',
                height: 300,
                width: '100%',
                toolbar: { show: false },
                background: 'transparent',
                fontFamily: 'DM Sans, ui-sans-serif',
                animations: { enabled: !reduceMotion, easing: 'easeinout', speed: 400 },
            },
            colors: ['#e91e8c', config.isDark ? '#a1a1aa' : '#52525b'],
            stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 6] },
            markers: { size: 3, strokeWidth: 0, hover: { size: 5 } },
            xaxis: {
                categories: summaryDaily.map((point) => point.label),
                labels: { style: { colors: config.textColor, fontSize: '11px' }, rotate: 0, hideOverlappingLabels: true },
                axisBorder: { show: false },
                axisTicks: { show: false },
                title: { text: 'Day of month', style: { color: config.textColor, fontSize: '11px', fontWeight: 500 } },
            },
            yaxis: {
                ...(amountMode ? {} : { min: 0 }),
                labels: { style: { colors: config.textColor, fontSize: '11px' }, formatter: (value) => formatSummaryAxisValue(value) },
                title: { text: amountMode ? `Amount (${summaryCurrencySymbol})` : 'Transactions', style: { color: config.textColor, fontSize: '11px', fontWeight: 500 } },
            },
            grid: { borderColor: config.gridColor, strokeDashArray: 3 },
            tooltip: { theme: config.isDark ? 'dark' : 'light', shared: false, intersect: true, custom: summaryTooltip },
            dataLabels: { enabled: false },
            legend: { show: true, position: 'top', horizontalAlign: 'left', labels: { colors: config.textColor } },
            theme: { mode: config.isDark ? 'dark' : 'light' },
        };
    }

    async function mountDashboardSummaryChart(ApexCharts, config) {
        const el = document.getElementById('chart-dashboard-summary');
        if (!el || !document.getElementById('main-layout')?.contains(el)) {
            return;
        }
        if (!Array.isArray(summaryDaily) || summaryDaily.length === 0) {
            clearSummaryChartLoading();
            el.setAttribute('aria-busy', 'false');
            setSummaryChartStatus('No daily comparison data is available.');
            return;
        }

        el.innerHTML = '';
        summaryChartConfig = config;
        summaryChart = new ApexCharts(el, summaryChartOptions(config));
        window.crmCharts?.register?.(chartGroup, 'chart-dashboard-summary', summaryChart);

        try {
            await summaryChart.render();
            el.setAttribute('aria-busy', 'false');
            setSummaryChartStatus('Comparison chart ready.');
        } catch (_) {
            el.setAttribute('aria-busy', 'false');
            setSummaryChartStatus('Chart visualization is unavailable. Use the daily summary data table below.');
        }
    }

    window.setDashboardSummaryMode = (mode) => {
        summaryMode = @json($amountVisible('charts')) && mode === 'amount' ? 'amount' : 'volume';
        if (!summaryChart || !summaryChartConfig) {
            return;
        }

        const options = summaryChartOptions(summaryChartConfig);
        Promise.all([
            summaryChart.updateSeries(options.series, true),
            summaryChart.updateOptions({ yaxis: options.yaxis, tooltip: options.tooltip }, false, true),
        ]).catch(() => {
            setSummaryChartStatus('Chart visualization is unavailable. Use the daily summary data table below.');
        });
    };

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
            clearSummaryChartLoading();
            setSummaryChartStatus('Chart visualization is unavailable. Use the daily summary data table below.');
            return;
        }

        const main = document.getElementById('main-layout');
        if (!main || (!main.querySelector('#chart-monthly-activity') && !main.querySelector('#chart-dashboard-summary'))) {
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
            mountDashboardSummaryChart(ApexCharts, config),
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
