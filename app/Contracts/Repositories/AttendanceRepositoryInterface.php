<?php

namespace App\Contracts\Repositories;

use App\Models\AttendanceLog;
use Illuminate\Support\Collection;

interface AttendanceRepositoryInterface
{
    public function log(int $userId, string $eventType, ?string $ipAddress = null, ?string $pauseCode = null): AttendanceLog;

    public function getLogs(?int $userId = null, ?string $date = null, int $limit = 50): Collection;

    public function getLastEvent(int $userId): ?AttendanceLog;
}
