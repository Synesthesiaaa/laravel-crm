<?php

namespace App\Contracts\Repositories;

use App\Models\AttendanceLog;
use Illuminate\Support\Collection;

interface AttendanceRepositoryInterface
{
    public function log(int $userId, string $eventType, ?string $ipAddress = null): AttendanceLog;

    public function logCustomEvent(
        int $userId,
        string $eventType,
        int $attendanceStatusTypeId,
        string $direction,
        ?string $ipAddress = null,
    ): AttendanceLog;

    public function getLogs(
        ?int $userId = null,
        ?string $date = null,
        int $limit = 50,
        ?string $eventFilter = null,
    ): Collection;

    public function getLastEvent(int $userId): ?AttendanceLog;
}
