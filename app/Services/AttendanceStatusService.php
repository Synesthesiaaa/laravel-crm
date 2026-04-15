<?php

namespace App\Services;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Models\AttendanceLog;
use App\Models\AttendanceStatusType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceStatusService
{
    public function __construct(
        protected AttendanceRepositoryInterface $attendanceRepository,
    ) {}

    /**
     * Start a custom attendance segment (e.g. lunch). Only one open segment at a time.
     */
    public function startStatus(User $user, string $code, ?string $ip): AttendanceLog
    {
        $type = AttendanceStatusType::query()->where('code', $code)->active()->first();
        if (! $type) {
            throw ValidationException::withMessages([
                'code' => [__('Invalid or inactive attendance status.')],
            ]);
        }

        if ($this->getOpenStatus($user) !== null) {
            throw ValidationException::withMessages([
                'code' => [__('End your current status before starting another.')],
            ]);
        }

        $eventType = $type->code.'_'.AttendanceLog::DIRECTION_START;

        return DB::transaction(function () use ($user, $type, $eventType, $ip) {
            return $this->attendanceRepository->logCustomEvent(
                $user->id,
                $eventType,
                $type->id,
                AttendanceLog::DIRECTION_START,
                $ip,
            );
        });
    }

    /**
     * End the currently open custom segment (same type as the open start).
     */
    public function endStatus(User $user, ?string $ip): AttendanceLog
    {
        $open = $this->getOpenStatus($user);
        if ($open === null) {
            throw ValidationException::withMessages([
                'status' => [__('No active attendance status to end.')],
            ]);
        }

        $type = $open->statusType;
        if (! $type) {
            throw ValidationException::withMessages([
                'status' => [__('Could not resolve attendance status type.')],
            ]);
        }

        $eventType = $type->code.'_'.AttendanceLog::DIRECTION_END;

        return DB::transaction(function () use ($user, $type, $eventType, $ip) {
            return $this->attendanceRepository->logCustomEvent(
                $user->id,
                $eventType,
                $type->id,
                AttendanceLog::DIRECTION_END,
                $ip,
            );
        });
    }

    /**
     * If there is an unmatched start today, return that log row; otherwise null.
     */
    public function getOpenStatus(User $user): ?AttendanceLog
    {
        $logs = AttendanceLog::query()
            ->where('user_id', $user->id)
            ->whereDate('event_time', today())
            ->whereNotNull('attendance_status_type_id')
            ->whereNotNull('direction')
            ->with('statusType')
            ->orderBy('event_time')
            ->orderBy('id')
            ->get();

        $open = null;
        foreach ($logs as $log) {
            if ($log->direction === AttendanceLog::DIRECTION_START) {
                $open = $log;
            } elseif ($log->direction === AttendanceLog::DIRECTION_END
                && $open !== null
                && (int) $log->attendance_status_type_id === (int) $open->attendance_status_type_id) {
                $open = null;
            }
        }

        return $open;
    }

    /**
     * Auto-insert end event before logout when a segment is still open.
     */
    public function autoCloseOnLogout(User $user, ?string $ip): void
    {
        if ($this->getOpenStatus($user) === null) {
            return;
        }

        try {
            $this->endStatus($user, $ip);
        } catch (ValidationException) {
            // Should not happen if getOpenStatus matched; ignore to not block logout.
        }
    }
}
