<?php

namespace App\Services\Telephony;

use App\Models\TelephonyAlert;
use Illuminate\Support\Facades\Log;

/**
 * Logs telephony-related alerts for monitoring and dashboard.
 */
class TelephonyAlertService
{
    public function log(
        string $type,
        string $message,
        array $context = [],
        string $severity = TelephonyAlert::SEVERITY_WARNING
    ): TelephonyAlert {
        $alert = TelephonyAlert::create([
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
        ]);

        $logLevel = $severity === TelephonyAlert::SEVERITY_CRITICAL ? 'error' : 'warning';
        Log::channel('telephony')->{$logLevel}("[{$type}] {$message}", $context);

        return $alert;
    }

    public function staleCorrected(int $sessionId, int $userId, string $dialedAt): TelephonyAlert
    {
        return $this->log(
            TelephonyAlert::TYPE_STALE_CORRECTED,
            "Forced stale call to failed: session_id=$sessionId",
            ['session_id' => $sessionId, 'user_id' => $userId, 'dialed_at' => $dialedAt],
            TelephonyAlert::SEVERITY_WARNING
        );
    }

    public function unmatchedAmiProcessed(int $eventId, int $sessionId): void
    {
        $this->log(
            TelephonyAlert::TYPE_UNMATCHED_AMI,
            "Matched previously unmatched AMI event: event_id=$eventId -> session_id=$sessionId",
            ['event_id' => $eventId, 'session_id' => $sessionId],
            TelephonyAlert::SEVERITY_INFO
        );
    }

    public function deadLetter(string $uuid, string $queue, string $exception): TelephonyAlert
    {
        return $this->log(
            TelephonyAlert::TYPE_DEAD_LETTER,
            "Telephony job failed: $queue",
            ['uuid' => $uuid, 'queue' => $queue, 'exception' => $exception],
            TelephonyAlert::SEVERITY_WARNING
        );
    }

    public function vicidialUnreachable(string $campaign, string $error): TelephonyAlert
    {
        return $this->log(
            TelephonyAlert::TYPE_VICIDIAL_UNREACHABLE,
            "VICIdial API unreachable: $campaign",
            ['campaign' => $campaign, 'error' => $error],
            TelephonyAlert::SEVERITY_WARNING
        );
    }

    /**
     * Count unresolved alerts in the last N hours.
     */
    public function countRecent(int $hours = 24): int
    {
        return TelephonyAlert::unresolved()->recent($hours)->count();
    }

    /**
     * Count by type in the last N hours.
     */
    public function countByType(int $hours = 24): array
    {
        return TelephonyAlert::recent($hours)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->all();
    }
}
