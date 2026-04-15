<?php

namespace App\Repositories;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Models\AttendanceLog;
use Illuminate\Support\Collection;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    public function log(int $userId, string $eventType, ?string $ipAddress = null): AttendanceLog
    {
        return AttendanceLog::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_time' => now(),
            'ip_address' => $ipAddress,
        ]);
    }

    public function logCustomEvent(
        int $userId,
        string $eventType,
        int $attendanceStatusTypeId,
        string $direction,
        ?string $ipAddress = null,
    ): AttendanceLog {
        return AttendanceLog::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'attendance_status_type_id' => $attendanceStatusTypeId,
            'direction' => $direction,
            'event_time' => now(),
            'ip_address' => $ipAddress,
        ]);
    }

    public function getLogs(
        ?int $userId = null,
        ?string $date = null,
        int $limit = 50,
        ?string $eventFilter = null,
    ): Collection {
        $q = AttendanceLog::with(['user', 'statusType'])->orderByDesc('event_time');
        if ($userId !== null) {
            $q->where('user_id', $userId);
        }
        if ($date !== null) {
            $q->whereDate('event_time', $date);
        }
        if ($eventFilter === 'login') {
            $q->where('event_type', 'login');
        } elseif ($eventFilter === 'logout') {
            $q->where('event_type', 'logout');
        } elseif ($eventFilter !== null && $eventFilter !== '' && is_numeric($eventFilter)) {
            $q->where('attendance_status_type_id', (int) $eventFilter);
        }

        return $q->limit($limit)->get();
    }

    public function getLastEvent(int $userId): ?AttendanceLog
    {
        return AttendanceLog::with('statusType')
            ->where('user_id', $userId)
            ->orderByDesc('event_time')
            ->first();
    }
}
