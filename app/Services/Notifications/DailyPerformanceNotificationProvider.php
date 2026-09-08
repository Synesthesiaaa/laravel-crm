<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRangeService;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyPerformanceNotificationProvider
{
    public function __construct(
        protected DashboardSalesRangeService $salesRangeService,
        protected DashboardStatsService $dashboardStats,
        protected DashboardLayoutService $dashboardLayout,
        protected NotificationLabelResolver $labels,
    ) {}

    public function item(User $user, string $campaignCode): NotificationItem
    {
        return $this->forDate(
            $user,
            $campaignCode,
            now(config('app.timezone'))->startOfDay(),
        );
    }

    /**
     * @return Collection<int, NotificationItem>
     */
    public function items(User $user, string $campaignCode, ?int $days = null): Collection
    {
        $days = max(1, min(
            $days ?? (int) config('notifications.activity_days', 30),
            (int) config('notifications.activity_days', 30),
        ));
        $today = now(config('app.timezone'))->startOfDay();

        return collect(range(0, $days - 1))
            ->map(fn (int $offset): NotificationItem => $this->forDate(
                $user,
                $campaignCode,
                $today->copy()->subDays($offset),
            ))
            ->values();
    }

    /**
     * @return list<string>
     */
    public function keys(string $campaignCode, ?int $days = null): array
    {
        $days = max(1, min(
            $days ?? (int) config('notifications.activity_days', 30),
            (int) config('notifications.activity_days', 30),
        ));
        $today = now(config('app.timezone'))->startOfDay();

        return collect(range(0, $days - 1))
            ->map(fn (int $offset): string => $this->key(
                $campaignCode,
                $today->copy()->subDays($offset)->toDateString(),
            ))
            ->all();
    }

    public function isDateAvailable(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            $resolved = Carbon::createFromFormat('!Y-m-d', $date, config('app.timezone'));
        } catch (\Throwable) {
            return false;
        }

        if ($resolved->format('Y-m-d') !== $date) {
            return false;
        }

        $today = now(config('app.timezone'))->startOfDay();
        $oldest = $today->copy()->subDays($this->historyDays() - 1);

        return $resolved->betweenIncluded($oldest, $today);
    }

    public function isCurrentDate(string $date): bool
    {
        return $date === now(config('app.timezone'))->toDateString();
    }

    private function forDate(User $user, string $campaignCode, Carbon $date): NotificationItem
    {
        $details = $this->build($user, $campaignCode, $date);
        $kpis = $details['kpis'];
        $amounts = $details['amounts'];
        $personalCount = $details['personal']['sales_count'];
        $teamCount = (int) ($kpis['sales'] ?? 0);
        $topAgent = $kpis['top_agent'] ?? null;
        $isToday = $details['is_today'];
        $period = $isToday ? 'today' : 'on '.$date->format('M j');
        $message = $teamCount > 0
            ? "You have {$personalCount} sales {$period}; team total is {$teamCount}."
            : "No sales yet. You have {$personalCount} sales {$period}; team total is 0.";
        if ($topAgent !== null) {
            $topAgentSummary = ' Top agent: '.$this->labels->agent((string) $topAgent)
                .' ('.number_format((int) ($kpis['top_agent_sales'] ?? 0)).' sales';
            if ($amounts['total']) {
                $topAgentSummary .= ', '.$this->formatAmount((float) ($kpis['top_agent_sales_amount'] ?? 0.0));
            }
            $message .= $topAgentSummary.').';
        }
        if ($amounts['total']) {
            $message .= ' Your value: '.$this->formatAmount((float) $details['personal']['sales_amount'])
                .'; team value: '.$this->formatAmount((float) ($kpis['sales_amount'] ?? 0.0)).'.';
        }

        return new NotificationItem(
            key: $this->key($campaignCode, $details['date']),
            category: 'performance',
            source: 'Daily performance',
            title: $isToday ? "Today's performance" : 'Daily performance · '.$date->format('M j, Y'),
            message: $message,
            occurredAt: $details['occurred_at'],
            type: $teamCount > 0 ? 'success' : 'info',
            meta: [
                'date' => $details['date'],
                'historical' => ! $isToday,
                'preview' => [
                    'personal_sales' => $personalCount,
                    'team_sales' => $teamCount,
                    'top_agent' => $topAgent === null ? null : $this->labels->agent((string) $topAgent),
                    'top_agent_sales' => (int) ($kpis['top_agent_sales'] ?? 0),
                    ...($amounts['total'] ? [
                        'personal_amount' => round((float) $details['personal']['sales_amount'], 2),
                        'team_amount' => round((float) ($kpis['sales_amount'] ?? 0.0), 2),
                        'currency' => $details['currency'],
                    ] : []),
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function details(User $user, string $campaignCode, ?string $expectedDate = null): array
    {
        $date = $expectedDate ?? now(config('app.timezone'))->toDateString();
        if (! $this->isDateAvailable($date)) {
            return [];
        }
        $businessDate = Carbon::createFromFormat('!Y-m-d', $date, config('app.timezone'));
        $details = $this->build($user, $campaignCode, $businessDate);
        $summaryAsOf = $details['is_today']
            ? now(config('app.timezone'))
            : $details['range']['until']->copy()->subSecond();
        $monthlySummary = $this->dashboardStats->getDashboardSummaryForCampaign($campaignCode, $summaryAsOf);

        $kpis = $details['kpis'];
        $amounts = $details['amounts'];
        $personal = $details['personal'];
        $personalMetrics = [
            ['label' => 'Sales count', 'value' => number_format((int) $personal['sales_count'])],
        ];
        if ($amounts['total']) {
            $personalMetrics[] = [
                'label' => 'Sales amount',
                'value' => $this->formatAmount((float) $personal['sales_amount']),
            ];
        }

        $teamMetrics = [
            ['label' => 'Sales count', 'value' => number_format((int) ($kpis['sales'] ?? 0))],
        ];
        if ($amounts['total']) {
            $teamMetrics[] = [
                'label' => 'Sales amount',
                'value' => $this->formatAmount((float) ($kpis['sales_amount'] ?? 0.0)),
            ];
        }

        $topAgent = $kpis['top_agent'] ?? null;
        $topMetrics = [
            ['label' => 'Top agent', 'value' => $topAgent === null ? 'No sales yet' : $this->labels->agent((string) $topAgent)],
            ['label' => 'Sales count', 'value' => number_format((int) ($kpis['top_agent_sales'] ?? 0))],
        ];
        if ($amounts['total']) {
            $topMetrics[] = [
                'label' => 'Sales amount',
                'value' => $this->formatAmount((float) ($kpis['top_agent_sales_amount'] ?? 0.0)),
            ];
        }

        $comparisonSection = $this->monthlyComparisonSection(
            $monthlySummary,
            $amounts['total'],
            $amounts['change'],
        );

        $formRows = [];
        foreach (($kpis['sales_by_form'] ?? []) as $form) {
            $formCode = (string) ($form['form_code'] ?? '');
            $row = [
                'name' => $this->labels->form($formCode, $campaignCode),
                'sales_count' => (int) ($form['sales'] ?? 0),
            ];
            if ($amounts['tables']) {
                $row['sales_amount'] = $this->formatAmount((float) ($form['sales_amount'] ?? 0.0));
            }
            $formRows[] = $row;
        }

        $leaderboard = [];
        foreach (($kpis['agent_leaderboard'] ?? []) as $row) {
            $leaderboardRow = [
                'agent' => $this->labels->agent((string) ($row['agent'] ?? '')),
                'sales_count' => (int) ($row['sales_count'] ?? 0),
            ];
            if ($amounts['tables']) {
                $leaderboardRow['sales_amount'] = $this->formatAmount((float) ($row['sales_amount'] ?? 0.0));
            }
            $leaderboard[] = $leaderboardRow;
        }

        return [
            'key' => $this->key($campaignCode, $details['date']),
            'category' => 'performance',
            'title' => $details['is_today']
                ? "Today's performance"
                : 'Daily performance · '.Carbon::parse($details['date'], config('app.timezone'))->format('M j, Y'),
            'description' => ($details['is_today'] ? 'Live' : 'Historical').' dashboard totals for '.$details['campaign_name'].'.',
            'date' => $details['date'],
            'range' => [
                'start' => $details['range']['start'],
                'end' => $details['range']['end'],
                'label' => $details['range']['label'],
            ],
            'updated_at' => $details['occurred_at']->toIso8601String(),
            'sections' => [
                ['title' => 'Your performance', 'metrics' => $personalMetrics],
                ['title' => 'Team total', 'metrics' => $teamMetrics],
                ['title' => 'Top agent', 'metrics' => $topMetrics],
                $comparisonSection,
                ...($formRows === [] ? [] : [['title' => 'Sales by form', 'rows' => $formRows]]),
                ...($leaderboard === [] ? [] : [['title' => 'Agent leaderboard', 'rows' => $leaderboard]]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{title: string, message: string, metrics: list<array{label: string, value: string}>}
     */
    private function monthlyComparisonSection(
        array $summary,
        bool $amountsTotalVisible,
        bool $amountsChangeVisible,
    ): array {
        $current = data_get($summary, 'summary.current', ['count' => 0, 'amount' => 0.0]);
        $previous = data_get($summary, 'summary.previous', ['count' => 0, 'amount' => 0.0]);
        $countComparison = data_get($summary, 'comparison.count', []);
        $amountComparison = data_get($summary, 'comparison.amount', []);
        $currentLabel = (string) data_get($summary, 'period.current.label', 'Current month');
        $previousLabel = (string) data_get($summary, 'period.previous.label', 'Previous month');
        $metrics = [
            ['label' => 'Current sales count', 'value' => number_format((int) ($current['count'] ?? 0))],
            ['label' => 'Previous sales count', 'value' => number_format((int) ($previous['count'] ?? 0))],
            ['label' => 'Sales count change', 'value' => $this->formatComparison($countComparison, false)],
        ];
        if ($amountsTotalVisible || $amountsChangeVisible) {
            $metrics[] = ['label' => 'Current sales amount', 'value' => $this->formatAmount((float) ($current['amount'] ?? 0.0))];
            $metrics[] = ['label' => 'Previous sales amount', 'value' => $this->formatAmount((float) ($previous['amount'] ?? 0.0))];
        }
        if ($amountsChangeVisible) {
            $metrics[] = ['label' => 'Sales amount change', 'value' => $this->formatComparison($amountComparison, true)];
        }

        return [
            'title' => 'Monthly comparison',
            'message' => $currentLabel.' compared with '.$previousLabel.'.',
            'metrics' => $metrics,
        ];
    }

    /**
     * @param  array<string, mixed>  $comparison
     */
    private function formatComparison(array $comparison, bool $amount): string
    {
        $difference = (float) ($comparison['difference'] ?? 0);
        $differenceText = $amount
            ? $this->formatSignedAmount($difference)
            : $this->formatSignedCount($difference);
        $percentage = $comparison['percentage'] ?? null;
        if ($percentage === null) {
            return $differenceText.' (New activity)';
        }

        $percentage = (float) $percentage;
        $percentageText = ($percentage > 0 ? '+' : '').number_format($percentage, 2).'%';

        return $differenceText.' ('.$percentageText.')';
    }

    public function key(string $campaignCode, string $date): string
    {
        return 'daily:'.rawurlencode($campaignCode).':'.$date;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(User $user, string $campaignCode, Carbon $date): array
    {
        $range = $this->salesRangeService->forDate($date);
        $kpis = $this->dashboardStats->getSalesKpisForCampaign(
            $campaignCode,
            $range['from'],
            $range['until'],
        );
        $layout = $this->dashboardLayout->getForCampaign($campaignCode);
        $amountsEnabled = (bool) data_get($layout, 'amounts.enabled', true);
        $personal = $this->resolvePersonalRow($user, $kpis['agent_leaderboard'] ?? []);
        $isToday = $range['date'] === now(config('app.timezone'))->toDateString();
        $occurredAt = $isToday
            ? now(config('app.timezone'))
            : $range['until']->copy()->subSecond();

        return [
            'date' => $range['date'],
            'range' => [
                'start' => $range['start'],
                'end' => $range['end'],
                'label' => Carbon::parse($range['from'])->format('M j, Y').' · '.$range['start'].'–'.$range['end'],
                'until' => $range['until'],
            ],
            'is_today' => $isToday,
            'occurred_at' => $occurredAt,
            'campaign_name' => $this->labels->campaign($campaignCode),
            'currency' => [
                'code' => (string) config('dashboard.currency_code', 'PHP'),
                'symbol' => (string) config('dashboard.currency_symbol', '₱'),
            ],
            'amounts' => [
                'total' => $amountsEnabled && (bool) data_get($layout, 'amounts.total', true),
                'change' => $amountsEnabled && (bool) data_get($layout, 'amounts.change', true),
                'tables' => $amountsEnabled && (bool) data_get($layout, 'amounts.tables', true),
            ],
            'personal' => $personal,
            'kpis' => $kpis,
        ];
    }

    private function historyDays(): int
    {
        return max(1, (int) config('notifications.activity_days', 30));
    }

    /**
     * @param  list<array<string, mixed>>  $leaderboard
     * @return array{sales_count: int, sales_amount: float}
     */
    private function resolvePersonalRow(User $user, array $leaderboard): array
    {
        $aliases = array_map('strtolower', $this->labels->aliases($user));
        $count = 0;
        $amount = 0.0;
        foreach ($leaderboard as $row) {
            $agent = strtolower(trim((string) ($row['agent'] ?? '')));
            if ($agent === '' || ! in_array($agent, $aliases, true)) {
                continue;
            }
            $count += (int) ($row['sales_count'] ?? 0);
            $amount += (float) ($row['sales_amount'] ?? 0.0);
        }

        return ['sales_count' => $count, 'sales_amount' => round($amount, 2)];
    }

    private function formatAmount(float $amount): string
    {
        $sign = $amount < 0 ? '-' : '';

        return $sign.(string) config('dashboard.currency_symbol', '₱').number_format(abs($amount), 2);
    }

    private function formatSignedAmount(float $amount): string
    {
        return ($amount > 0 ? '+' : ($amount < 0 ? '-' : '')).$this->formatAmount(abs($amount));
    }

    private function formatSignedCount(float $count): string
    {
        return ($count > 0 ? '+' : ($count < 0 ? '-' : '')).number_format(abs($count));
    }
}
