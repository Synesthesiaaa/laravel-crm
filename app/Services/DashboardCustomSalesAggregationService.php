<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Shared custom-sale rule evaluation and bounded source aggregation. */
class DashboardCustomSalesAggregationService
{
    /** @param list<array<string, mixed>> $forms */
    public function salesByAgentInRange(array $forms, Carbon $from, Carbon $until): array
    {
        return $this->aggregateByAgent($forms, 'created_at', $from, $until, false, true);
    }

    /** @param list<array<string, mixed>> $forms */
    public function salesByAgentSince(array $forms, Carbon $since): array
    {
        return $this->aggregateByAgent($forms, 'created_at', $since, now(), true, false);
    }

    /** @param list<array<string, mixed>> $forms */
    public function salesByAgentInDateRange(array $forms, string $fromYmd, string $toYmd): array
    {
        return $this->aggregateByAgent($forms, 'date', $fromYmd, $toYmd, true, false);
    }

    /** @param list<array<string, mixed>> $forms */
    public function dailyCampaignReport(array $forms, Carbon $businessDate, string $date): array
    {
        $formColumns = array_map(static fn (array $form): array => [
            'code' => $form['form_code'],
            'name' => $form['form_name'],
        ], $forms);
        $emptyTotals = $this->emptyReportTotals($formColumns);

        if ($forms === []) {
            return [
                'date' => $date,
                'forms' => $formColumns,
                'daily' => [],
                'month_to_date' => [],
                'totals' => ['daily' => $emptyTotals, 'month_to_date' => $emptyTotals],
            ];
        }

        $monthStart = $businessDate->copy()->startOfMonth()->toDateString();
        $dailyRows = [];
        $monthToDateRows = [];
        $dailyTotals = $this->emptyReportTotals($formColumns);
        $monthToDateTotals = $this->emptyReportTotals($formColumns);

        foreach ($forms as $form) {
            $table = $form['table'];
            if (! Schema::hasColumn($table, 'date') || ! Schema::hasColumn($table, 'agent')) {
                continue;
            }

            $conditionFields = array_column($form['conditions'], 'field_name');
            $select = array_values(array_unique(array_merge(
                ['id', 'date', 'agent'],
                $conditionFields,
                $form['amount_field'] === null ? [] : [$form['amount_field']],
            )));
            DB::table($table)
                ->whereBetween('date', [$monthStart, $date])
                ->select($select)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$dailyRows, &$monthToDateRows, &$dailyTotals, &$monthToDateTotals, $form, $formColumns, $date): void {
                    foreach ($rows as $row) {
                        if (! $this->matchesConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                            continue;
                        }

                        $agent = trim((string) ($row->agent ?? ''));
                        if ($agent === '') {
                            continue;
                        }

                        $amount = $this->amount($row, $form['amount_field']);
                        $this->recordReportEntry($monthToDateRows, $monthToDateTotals, $formColumns, $form['form_code'], $agent, $amount);
                        if ((string) $row->date === $date) {
                            $this->recordReportEntry($dailyRows, $dailyTotals, $formColumns, $form['form_code'], $agent, $amount);
                        }
                    }
                });
        }

        return [
            'date' => $date,
            'forms' => $formColumns,
            'daily' => $this->normalizeReportRows($dailyRows),
            'month_to_date' => $this->normalizeReportRows($monthToDateRows),
            'totals' => [
                'daily' => $this->normalizeReportTotals($dailyTotals),
                'month_to_date' => $this->normalizeReportTotals($monthToDateTotals),
            ],
        ];
    }

    /** @param list<array<string, mixed>> $forms @return list<array{table: string, rule: array<string, mixed>, select: list<string>}> */
    public function summarySources(array $forms): array
    {
        $sources = [];
        foreach ($forms as $form) {
            $fields = array_merge(
                array_column($form['conditions'], 'field_name'),
                $form['amount_field'] === null ? [] : [$form['amount_field']],
            );
            $sources[] = [
                'table' => $form['table'],
                'rule' => $form,
                'select' => array_values(array_unique(['id', 'created_at', ...$fields])),
            ];
        }

        return $sources;
    }

    /** @param list<array{field_name: string, accepted_values: list<string>}> $conditions */
    public function matchesConditions(
        object $row,
        array $conditions,
        ?string $markerField = null,
        string $trigger = DashboardSalesRuleService::TRIGGER_TAG,
    ): bool {
        if ($trigger === DashboardSalesRuleService::TRIGGER_FORM) {
            return true;
        }

        if ($trigger === DashboardSalesRuleService::TRIGGER_MARKED_AMOUNT) {
            return $markerField !== null && $this->hasNumericValue($row->{$markerField} ?? null);
        }

        foreach ($conditions as $condition) {
            $value = $row->{$condition['field_name']} ?? null;
            if ($value !== null && in_array($this->normalizeTag((string) $value), $condition['accepted_values'], true)) {
                return true;
            }
        }

        return false;
    }

    public function amount(object $row, ?string $amountField): float
    {
        if ($amountField === null) {
            return 0.0;
        }

        $value = $row->{$amountField} ?? null;
        if ($value === null || (is_string($value) && trim($value) === '') || ! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    /** @param list<array<string, mixed>> $forms */
    private function aggregateByAgent(array $forms, string $dateColumn, Carbon|string $from, Carbon|string $until, bool $inclusiveEnd, bool $withFormBreakdown): array
    {
        $sales = 0;
        $amount = 0.0;
        $counts = [];
        $amounts = [];
        $salesByForm = [];

        foreach ($forms as $form) {
            $table = $form['table'];
            if ($withFormBreakdown) {
                $salesByForm[$table] = [
                    'form_code' => $form['form_code'],
                    'form_name' => $form['form_name'],
                    'sales' => 0,
                    'sales_amount' => 0.0,
                ];
            }
            if (! Schema::hasColumn($table, $dateColumn)) {
                continue;
            }

            $conditionFields = array_column($form['conditions'], 'field_name');
            $hasAgent = Schema::hasColumn($table, 'agent');
            $select = array_values(array_unique(array_merge(
                ['id'],
                $conditionFields,
                $form['amount_field'] === null ? [] : [$form['amount_field']],
                $hasAgent ? ['agent'] : [],
            )));
            $query = DB::table($table)->where($dateColumn, '>=', $from);
            $query = $inclusiveEnd
                ? $query->where($dateColumn, '<=', $until)
                : $query->where($dateColumn, '<', $until);
            $query->select($select)->orderBy('id')->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, &$salesByForm, $form, $table, $hasAgent, $withFormBreakdown): void {
                foreach ($rows as $row) {
                    if (! $this->matchesConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                        continue;
                    }

                    $saleAmount = $this->amount($row, $form['amount_field']);
                    $sales++;
                    $amount += $saleAmount;
                    if ($withFormBreakdown) {
                        $salesByForm[$table]['sales']++;
                        $salesByForm[$table]['sales_amount'] += $saleAmount;
                    }

                    if (! $hasAgent) {
                        continue;
                    }
                    $agent = trim((string) ($row->agent ?? ''));
                    if ($agent === '') {
                        continue;
                    }

                    $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                    $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                }
            });
        }

        if ($withFormBreakdown) {
            foreach ($salesByForm as &$form) {
                $form['sales_amount'] = round($form['sales_amount'], 2);
            }
            unset($form);
        }

        return [
            'count' => $sales,
            'amount' => $amount,
            'counts' => $counts,
            'amounts' => $amounts,
            ...($withFormBreakdown ? ['sales_by_form' => array_values($salesByForm)] : []),
        ];
    }

    private function hasNumericValue(mixed $value): bool
    {
        return $value !== null && (! is_string($value) || trim($value) !== '') && is_numeric($value);
    }

    private function normalizeTag(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /** @param list<array{code: string, name: string}> $forms */
    private function emptyReportTotals(array $forms): array
    {
        return [
            'counts' => array_fill_keys(array_column($forms, 'code'), 0),
            'amounts' => array_fill_keys(array_column($forms, 'code'), 0.0),
            'total_count' => 0,
            'total_amount' => 0.0,
        ];
    }

    private function recordReportEntry(array &$rows, array &$totals, array $forms, string $formCode, string $agent, float $amount): void
    {
        if (! isset($rows[$agent])) {
            $rows[$agent] = ['agent' => $agent, ...$this->emptyReportTotals($forms)];
        }
        $rows[$agent]['counts'][$formCode]++;
        $rows[$agent]['amounts'][$formCode] += $amount;
        $rows[$agent]['total_count']++;
        $rows[$agent]['total_amount'] += $amount;
        $totals['counts'][$formCode]++;
        $totals['amounts'][$formCode] += $amount;
        $totals['total_count']++;
        $totals['total_amount'] += $amount;
    }

    private function normalizeReportRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['total_amount'] = round($row['total_amount'], 2);
            foreach ($row['amounts'] as &$amount) {
                $amount = round($amount, 2);
            }
            unset($amount);
        }
        unset($row);
        ksort($rows, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($rows);
    }

    private function normalizeReportTotals(array $totals): array
    {
        foreach ($totals['amounts'] as &$amount) {
            $amount = round($amount, 2);
        }
        unset($amount);
        $totals['total_amount'] = round($totals['total_amount'], 2);

        return $totals;
    }
}
