<?php

namespace Tests\Unit\Services;

use App\Models\DataRetentionPolicy;
use App\Services\DataRetentionScheduleService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DataRetentionScheduleServiceTest extends TestCase
{
    public function test_it_calculates_the_next_daily_occurrence(): void
    {
        $policy = new DataRetentionPolicy([
            'run_mode' => 'recurring',
            'recurrence' => 'daily',
            'run_time' => '09:15',
        ]);

        $service = app(DataRetentionScheduleService::class);

        $this->assertSame(
            '2026-08-04 09:15:00',
            $service->nextRunAt($policy, CarbonImmutable::parse('2026-08-04 09:14:00'))->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-08-05 09:15:00',
            $service->nextRunAt($policy, CarbonImmutable::parse('2026-08-04 09:15:00'))->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_calculates_the_next_weekly_occurrence(): void
    {
        $policy = new DataRetentionPolicy([
            'run_mode' => 'recurring',
            'recurrence' => 'weekly',
            'run_time' => '09:15',
            'run_day_of_week' => 1,
        ]);

        $nextRunAt = app(DataRetentionScheduleService::class)->nextRunAt(
            $policy,
            CarbonImmutable::parse('2026-08-04 10:00:00'),
        );

        $this->assertSame('2026-08-10 09:15:00', $nextRunAt->format('Y-m-d H:i:s'));
    }

    public function test_it_clamps_a_monthly_occurrence_to_the_last_day_of_the_month(): void
    {
        $policy = new DataRetentionPolicy([
            'run_mode' => 'recurring',
            'recurrence' => 'monthly',
            'run_time' => '09:15',
            'run_day_of_month' => 31,
        ]);

        $nextRunAt = app(DataRetentionScheduleService::class)->nextRunAt(
            $policy,
            CarbonImmutable::parse('2026-01-31 10:00:00'),
        );

        $this->assertSame('2026-02-28 09:15:00', $nextRunAt->format('Y-m-d H:i:s'));
    }

    public function test_it_returns_the_one_time_run_at_value(): void
    {
        $policy = new DataRetentionPolicy([
            'run_mode' => 'once',
            'run_at' => '2026-08-04 09:15:00',
        ]);

        $nextRunAt = app(DataRetentionScheduleService::class)->nextRunAt(
            $policy,
            CarbonImmutable::parse('2026-08-01 10:00:00'),
        );

        $this->assertSame('2026-08-04 09:15:00', $nextRunAt->format('Y-m-d H:i:s'));
    }
}
