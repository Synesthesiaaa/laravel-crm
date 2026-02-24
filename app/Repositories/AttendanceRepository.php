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

    public function getLogs(?int $userId = null, ?string $date = null, int $limit = 50): Collection
    {
        $q = AttendanceLog::with('user')->orderByDesc('event_time');
        if ($userId !== null) {
            $q->where('user_id', $userId);
        }
        if ($date !== null) {
            $q->whereDate('event_time', $date);
        }
        return $q->limit($limit)->get();
    }

    public function getLastEvent(int $userId): ?AttendanceLog
    {
        return AttendanceLog::where('user_id', $userId)->orderByDesc('event_time')->first();
    }
}
