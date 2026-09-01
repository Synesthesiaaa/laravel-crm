<?php

namespace Tests\Unit\Services;

use App\Services\DashboardPeriodService;
use Carbon\Carbon;
use Tests\TestCase;

class DashboardPeriodServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Manila']);
    }

    public function test_month_to_date_uses_equivalent_previous_elapsed_range(): void
    {
        $periods = app(DashboardPeriodService::class)->monthToDate(
            Carbon::create(2026, 9, 18, 15, 30, 0, 'Asia/Manila'),
        );

        $this->assertSame('month_to_date', $periods['mode']);
        $this->assertSame('2026-09-01 00:00:00', $periods['current']['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-18 15:30:00', $periods['current']['end']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-01 00:00:00', $periods['previous']['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-18 15:30:00', $periods['previous']['end']->format('Y-m-d H:i:s'));
        $this->assertSame('Sep 1, 2026 - Sep 18, 2026', $periods['current']['label']);
        $this->assertSame('Aug 1, 2026 - Aug 18, 2026', $periods['previous']['label']);
    }

    public function test_month_to_date_caps_non_leap_february(): void
    {
        $periods = app(DashboardPeriodService::class)->monthToDate(
            Carbon::create(2025, 3, 31, 12, 0, 0, 'Asia/Manila'),
        );

        $this->assertSame('2025-02-28 12:00:00', $periods['previous']['end']->format('Y-m-d H:i:s'));
        $this->assertSame(28, $periods['previous']['day_count']);
    }

    public function test_month_to_date_caps_leap_february_and_30_day_previous_months(): void
    {
        $leapYear = app(DashboardPeriodService::class)->monthToDate(
            Carbon::create(2024, 3, 31, 12, 0, 0, 'Asia/Manila'),
        );
        $thirtyDayMonth = app(DashboardPeriodService::class)->monthToDate(
            Carbon::create(2026, 5, 31, 12, 0, 0, 'Asia/Manila'),
        );

        $this->assertSame('2024-02-29 12:00:00', $leapYear['previous']['end']->format('Y-m-d H:i:s'));
        $this->assertSame(29, $leapYear['previous']['day_count']);
        $this->assertSame('2026-04-30 12:00:00', $thirtyDayMonth['previous']['end']->format('Y-m-d H:i:s'));
        $this->assertSame(30, $thirtyDayMonth['previous']['day_count']);
    }

    public function test_completed_month_uses_two_full_calendar_months(): void
    {
        $periods = app(DashboardPeriodService::class)->completedMonth(
            Carbon::create(2026, 8, 15, 12, 0, 0, 'Asia/Manila'),
        );

        $this->assertSame('completed_month', $periods['mode']);
        $this->assertSame('2026-08-01 00:00:00', $periods['current']['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', $periods['current']['end']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 00:00:00', $periods['previous']['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-01 00:00:00', $periods['previous']['end']->format('Y-m-d H:i:s'));
        $this->assertSame(31, $periods['previous']['day_count']);
    }

    public function test_compare_returns_direction_and_percentage(): void
    {
        $service = app(DashboardPeriodService::class);

        $this->assertSame([
            'difference' => 250,
            'percentage' => 25.0,
            'status' => 'increase',
            'message' => 'vs last month',
        ], $service->compare(1250, 1000));
        $this->assertSame('decrease', $service->compare(90, 100)['status']);
        $this->assertSame('unchanged', $service->compare(100, 100)['status']);
    }

    public function test_compare_handles_zero_previous_values_without_dividing_by_zero(): void
    {
        $service = app(DashboardPeriodService::class);

        $newActivity = $service->compare(50, 0);
        $noActivity = $service->compare(0.0, 0.0);

        $this->assertSame(50, $newActivity['difference']);
        $this->assertNull($newActivity['percentage']);
        $this->assertSame('new', $newActivity['status']);
        $this->assertSame('New activity vs last month', $newActivity['message']);
        $this->assertSame(0.0, $noActivity['difference']);
        $this->assertSame(0.0, $noActivity['percentage']);
        $this->assertSame('unchanged', $noActivity['status']);
    }
}
