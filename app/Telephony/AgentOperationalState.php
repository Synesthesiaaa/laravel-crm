<?php

namespace App\Telephony;

enum AgentOperationalState: string
{
    case Available = 'AVAILABLE';
    case OnCall = 'ON_CALL';
    case Paused = 'PAUSED';
    case Ringing = 'RINGING';
    case Queue = 'QUEUE';
    case Offline = 'OFFLINE';
    case Unknown = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::OnCall => 'On Call',
            self::Paused => 'Paused',
            self::Ringing => 'Ringing',
            self::Queue => 'Queue',
            self::Offline => 'Offline',
            self::Unknown => 'Unknown',
        };
    }
}
