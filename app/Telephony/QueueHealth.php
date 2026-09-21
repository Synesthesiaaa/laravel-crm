<?php

namespace App\Telephony;

enum QueueHealth: string
{
    case Healthy = 'HEALTHY';
    case Warning = 'WARNING';
    case Critical = 'CRITICAL';
    case Unknown = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::Unknown => 'Unknown',
        };
    }
}
