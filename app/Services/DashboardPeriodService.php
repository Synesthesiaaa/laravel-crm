<?php

namespace App\Services;

use Carbon\Carbon;

class DashboardPeriodService
{
    /**
     * Resolve the current month-to-date period and its equivalent previous-month period.
     *
     * @return array{mode: string, current: array{start: Carbon, end: Carbon, label: string, day_count: int}, previous: array{start: Carbon, end: Carbon, label: string, day_count: int}}
     */
    public function monthToDate(?Carbon $asOf = null): array
    {
        $asOf = $this->inApplicationTimezone($asOf ?? now(config('app.timezone')));
        $currentStart = $asOf->copy()->startOfMonth();
        $previousStart = $currentStart->copy()->subMonthNoOverflow();
        $previousDay = min($asOf->day, $previousStart->daysInMonth);
        $previousEnd = $this->atEquivalentTime(
            $previousStart->copy()->addDays($previousDay - 1),
            $asOf,
        );

        return [
            'mode' => 'month_to_date',
            'current' => [
                'start' => $currentStart,
                'end' => $asOf->copy(),
                'label' => $this->dateLabel($currentStart, $asOf),
                'day_count' => $asOf->day,
            ],
            'previous' => [
                'start' => $previousStart,
                'end' => $previousEnd,
                'label' => $this->dateLabel($previousStart, $previousEnd),
                'day_count' => $previousDay,
            ],
        ];
    }

    /**
     * Resolve a completed calendar month and the previous completed calendar month.
     *
     * @return array{mode: string, current: array{start: Carbon, end: Carbon, label: string, day_count: int}, previous: array{start: Carbon, end: Carbon, label: string, day_count: int}}
     */
    public function completedMonth(Carbon $month): array
    {
        $currentStart = $this->inApplicationTimezone($month)->startOfMonth();
        $currentEnd = $currentStart->copy()->addMonthNoOverflow();
        $previousStart = $currentStart->copy()->subMonthNoOverflow();
        $previousEnd = $currentStart->copy();

        return [
            'mode' => 'completed_month',
            'current' => [
                'start' => $currentStart,
                'end' => $currentEnd,
                'label' => $this->dateLabel($currentStart, $currentEnd->copy()->subSecond()),
                'day_count' => $currentStart->daysInMonth,
            ],
            'previous' => [
                'start' => $previousStart,
                'end' => $previousEnd,
                'label' => $this->dateLabel($previousStart, $previousEnd->copy()->subSecond()),
                'day_count' => $previousStart->daysInMonth,
            ],
        ];
    }

    /**
     * Compare two numeric values without producing an infinite percentage.
     *
     * @return array{difference: int|float, percentage: float|null, status: string, message: string}
     */
    public function compare(int|float $current, int|float $previous): array
    {
        $difference = $current - $previous;

        if ($previous == 0) {
            if ($current == 0) {
                return [
                    'difference' => $difference,
                    'percentage' => 0.0,
                    'status' => 'unchanged',
                    'message' => 'No change vs last month',
                ];
            }

            return [
                'difference' => $difference,
                'percentage' => null,
                'status' => 'new',
                'message' => 'New activity vs last month',
            ];
        }

        $percentage = round(($difference / $previous) * 100, 2);
        $status = $difference > 0 ? 'increase' : ($difference < 0 ? 'decrease' : 'unchanged');

        return [
            'difference' => $difference,
            'percentage' => $percentage,
            'status' => $status,
            'message' => $status === 'unchanged' ? 'No change vs last month' : 'vs last month',
        ];
    }

    private function inApplicationTimezone(Carbon $date): Carbon
    {
        return $date->copy()->setTimezone(config('app.timezone'));
    }

    private function atEquivalentTime(Carbon $date, Carbon $time): Carbon
    {
        return $date->setTime($time->hour, $time->minute, $time->second, $time->microsecond);
    }

    private function dateLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('M j, Y').' - '.$end->format('M j, Y');
    }
}
