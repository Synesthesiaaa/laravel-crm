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
        return DB::transaction(function () use ($user, $code, $ip) {
            $this->lockUserState($user);

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

            return $this->attendanceRepository->logCustomEvent(
                $user->id,
                $type->code.'_'.AttendanceLog::DIRECTION_START,
                $type->id,
                AttendanceLog::DIRECTION_START,
                $ip,
            );
        }, attempts: 3);
    }

    /**
     * End the currently open custom segment (same type as the open start).
     */
    public function endStatus(User $user, ?string $ip): AttendanceLog
    {
        return DB::transaction(function () use ($user, $ip): AttendanceLog {
            $this->lockUserState($user);
            $open = $this->getOpenStatus($user);
            if ($open === null) {
                throw ValidationException::withMessages([
                    'status' => [__('No active attendance status to end.')],
                ]);
            }

            return $this->appendCloseStatus($user, $open, $ip);
        }, attempts: 3);
    }

    private function appendCloseStatus(User $user, AttendanceLog $open, ?string $ip): AttendanceLog
    {
        $type = $open->statusType;
        if (! $type) {
            throw ValidationException::withMessages([
                'status' => [__('Could not resolve attendance status type.')],
            ]);
        }

        $eventType = $type->code.'_'.AttendanceLog::DIRECTION_END;

        return $this->attendanceRepository->logCustomEvent(
            $user->id,
            $eventType,
            $type->id,
            AttendanceLog::DIRECTION_END,
            $ip,
        );
    }

    /**
     * Return the most recently opened custom status that has no matching end.
     */
    public function getOpenStatus(User $user): ?AttendanceLog
    {
        $latestEventIds = AttendanceLog::query()
            ->selectRaw('MAX(id) as latest_id')
            ->where('user_id', $user->id)
            ->whereNotNull('attendance_status_type_id')
            ->whereNotNull('direction')
            ->groupBy('attendance_status_type_id')
            ->pluck('latest_id');

        return AttendanceLog::query()
            ->select(['id', 'user_id', 'attendance_status_type_id', 'direction', 'event_time'])
            ->whereIn('id', $latestEventIds)
            ->where('direction', AttendanceLog::DIRECTION_START)
            ->orderByDesc('id')
            ->first()
            ?->loadMissing('statusType');
    }

    /**
     * Auto-insert end event before logout when a segment is still open.
     */
    public function autoCloseOnLogout(User $user, ?string $ip): void
    {
        try {
            DB::transaction(function () use ($user, $ip): void {
                $this->lockUserState($user);
                $open = $this->getOpenStatus($user);
                if ($open !== null) {
                    $this->appendCloseStatus($user, $open, $ip);
                }
            }, attempts: 3);
        } catch (ValidationException) {
            // Do not block logout if status metadata was concurrently changed.
        }
    }

    private function lockUserState(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
    }
}
