<?php

namespace App\Services;

use App\Models\DataRetentionPolicy;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class DataRetentionScheduleService
{
    public function nextRunAt(DataRetentionPolicy $policy, CarbonImmutable $from): CarbonImmutable
    {
        if ($policy->run_mode === 'once') {
            if ($policy->run_at instanceof DateTimeInterface) {
                return CarbonImmutable::instance($policy->run_at);
            }

            return CarbonImmutable::parse((string) $policy->run_at);
        }

        [$hour, $minute] = $this->runTime($policy);
        $candidate = $from->setTime($hour, $minute);

        return match ($policy->recurrence) {
            'daily' => $candidate->lessThanOrEqualTo($from)
                ? $candidate->addDay()
                : $candidate,
            'weekly' => $this->nextWeeklyRunAt($policy, $from, $hour, $minute),
            'monthly' => $this->nextMonthlyRunAt($policy, $from, $hour, $minute),
            default => throw new InvalidArgumentException('Unsupported retention recurrence.'),
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function runTime(DataRetentionPolicy $policy): array
    {
        $runTime = (string) $policy->run_time;
        [$hour, $minute] = array_map('intval', array_pad(explode(':', $runTime), 2, ''));

        if ($runTime === '' || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidArgumentException('Invalid retention run time.');
        }

        return [$hour, $minute];
    }

    private function nextWeeklyRunAt(
        DataRetentionPolicy $policy,
        CarbonImmutable $from,
        int $hour,
        int $minute,
    ): CarbonImmutable {
        $dayOfWeek = (int) $policy->run_day_of_week;

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            throw new InvalidArgumentException('Invalid retention weekday.');
        }

        $candidate = $from->startOfWeek()->addDays($dayOfWeek - 1)->setTime($hour, $minute);

        return $candidate->lessThanOrEqualTo($from)
            ? $candidate->addWeek()
            : $candidate;
    }

    private function nextMonthlyRunAt(
        DataRetentionPolicy $policy,
        CarbonImmutable $from,
        int $hour,
        int $minute,
    ): CarbonImmutable {
        $dayOfMonth = (int) $policy->run_day_of_month;

        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            throw new InvalidArgumentException('Invalid retention day of month.');
        }

        $candidate = $this->monthlyCandidate($from, $dayOfMonth, $hour, $minute);

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate = $this->monthlyCandidate($from->addMonthNoOverflow(), $dayOfMonth, $hour, $minute);
        }

        return $candidate;
    }

    private function monthlyCandidate(
        CarbonImmutable $date,
        int $dayOfMonth,
        int $hour,
        int $minute,
    ): CarbonImmutable {
        return $date->startOfMonth()
            ->setDay(min($dayOfMonth, $date->daysInMonth))
            ->setTime($hour, $minute);
    }
}
