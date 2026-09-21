<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardSalesRangeService
{
    /**
     * Resolve the dashboard's optional date/time filters, falling back to the
     * current application-timezone business window.
     *
     * @return array{date: string, start: string, end: string, from: Carbon, until: Carbon}
     */
    public function resolve(Request $request): array
    {
        $date = $request->query('sales_date');
        $start = $request->query('sales_start');
        $end = $request->query('sales_end');

        if ($date === null && $start === null && $end === null) {
            return $this->default();
        }

        if (! is_string($date) || ! is_string($start) || ! is_string($end)) {
            return $this->default();
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || ! preg_match('/^\d{2}:\d{2}$/', $start)
            || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return $this->default();
        }

        try {
            $from = Carbon::createFromFormat('!Y-m-d H:i', "{$date} {$start}", config('app.timezone'));
            $until = Carbon::createFromFormat('!Y-m-d H:i', "{$date} {$end}", config('app.timezone'));
        } catch (\Throwable) {
            return $this->default();
        }

        if ($from->format('Y-m-d') !== $date
            || $from->format('H:i') !== $start
            || $until->format('Y-m-d') !== $date
            || $until->format('H:i') !== $end
            || $until->lte($from)) {
            return $this->default();
        }

        return [
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'from' => $from,
            'until' => $until,
        ];
    }

    /**
     * @return array{date: string, start: string, end: string, from: Carbon, until: Carbon}
     */
    public function default(): array
    {
        $date = now(config('app.timezone'))->toDateString();

        return $this->businessDay($date);
    }

    /**
     * Resolve the dashboard's default business window for a specific
     * application-timezone date.
     *
     * @return array{date: string, start: string, end: string, from: Carbon, until: Carbon}
     */
    public function forDate(Carbon|string $date): array
    {
        $date = $date instanceof Carbon
            ? $date->copy()->setTimezone(config('app.timezone'))->toDateString()
            : trim($date);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->default();
        }

        try {
            $resolved = Carbon::createFromFormat('!Y-m-d', $date, config('app.timezone'));
        } catch (\Throwable) {
            return $this->default();
        }

        if ($resolved->format('Y-m-d') !== $date) {
            return $this->default();
        }

        return $this->businessDay($date);
    }

    /**
     * @return array{date: string, start: string, end: string, from: Carbon, until: Carbon}
     */
    private function businessDay(string $date): array
    {
        $from = Carbon::createFromFormat('!Y-m-d H:i', "{$date} 06:00", config('app.timezone'));
        $until = Carbon::createFromFormat('!Y-m-d H:i', "{$date} 18:00", config('app.timezone'));

        return [
            'date' => $date,
            'start' => '06:00',
            'end' => '18:00',
            'from' => $from,
            'until' => $until,
        ];
    }
}
