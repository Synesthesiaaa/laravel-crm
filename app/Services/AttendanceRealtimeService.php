<?php

namespace App\Services;

use App\Models\AttendanceLog;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class AttendanceRealtimeService
{
    /**
     * @return array{
     *     sessions: list<array<string, mixed>>,
     *     stats: array{online: int, available: int, away: int, longest_session_seconds: int},
     *     generated_at: string
     * }
     */
    public function snapshot(): array
    {
        $now = now();
        $maxSessionHours = max(1, (int) config('attendance.realtime.max_session_hours', 24));
        $cutoff = $now->copy()->subHours($maxSessionHours);
        $activeLogins = $this->activeLoginLogs($cutoff);
        $userIds = $activeLogins->pluck('user_id')->all();
        $earliestLogin = $activeLogins->min('event_time');

        $statusLogs = $userIds === [] || $earliestLogin === null
            ? collect()
            : AttendanceLog::query()
                ->select([
                    'id',
                    'user_id',
                    'attendance_status_type_id',
                    'direction',
                    'event_time',
                ])
                ->with('statusType:id,label')
                ->whereIn('user_id', $userIds)
                ->where('event_time', '>=', $earliestLogin)
                ->whereNotNull('attendance_status_type_id')
                ->whereNotNull('direction')
                ->orderBy('event_time')
                ->orderBy('id')
                ->get()
                ->groupBy('user_id');

        $sessions = $activeLogins
            ->map(fn (AttendanceLog $login): array => $this->sessionFromLogin(
                $login,
                $statusLogs->get($login->user_id, collect()),
                $now,
            ))
            ->sortByDesc('session_duration_seconds')
            ->values();

        return [
            'sessions' => $sessions->all(),
            'stats' => [
                'online' => $sessions->count(),
                'available' => $sessions->where('attendance_code', 'available')->count(),
                'away' => $sessions->where('attendance_code', '!=', 'available')->count(),
                'longest_session_seconds' => (int) ($sessions->max('session_duration_seconds') ?? 0),
            ],
            'generated_at' => $now->toIso8601String(),
            'max_session_hours' => $maxSessionHours,
        ];
    }

    /**
     * Return each user's latest system event only when that event is login.
     *
     * @return Collection<int, AttendanceLog>
     */
    private function activeLoginLogs($cutoff): Collection
    {
        return AttendanceLog::query()
            ->from('attendance_logs as current')
            ->select([
                'current.id',
                'current.user_id',
                'current.event_type',
                'current.event_time',
                'current.ip_address',
            ])
            ->with('user:id,username,full_name,role')
            ->where('current.event_type', 'login')
            ->where('current.event_time', '>=', $cutoff)
            ->whereNotExists(function (QueryBuilder $query): void {
                $query
                    ->selectRaw('1')
                    ->from('attendance_logs as newer')
                    ->whereColumn('newer.user_id', 'current.user_id')
                    ->whereIn('newer.event_type', AttendanceLog::SYSTEM_EVENT_TYPES)
                    ->where(function (QueryBuilder $newer): void {
                        $newer
                            ->whereColumn('newer.event_time', '>', 'current.event_time')
                            ->orWhere(function (QueryBuilder $sameTime): void {
                                $sameTime
                                    ->whereColumn('newer.event_time', '=', 'current.event_time')
                                    ->whereColumn('newer.id', '>', 'current.id');
                            });
                    });
            })
            ->get();
    }

    /**
     * @param  Collection<int, AttendanceLog>  $statusLogs
     * @return array<string, mixed>
     */
    private function sessionFromLogin(AttendanceLog $login, Collection $statusLogs, $now): array
    {
        $currentStatus = null;
        $statusSince = $login->event_time;

        foreach ($statusLogs as $log) {
            if ($log->event_time === null || $login->event_time === null || $log->event_time->lt($login->event_time)) {
                continue;
            }

            if ($log->direction === AttendanceLog::DIRECTION_START) {
                $currentStatus = $log;
                $statusSince = $log->event_time;

                continue;
            }

            if (
                $log->direction === AttendanceLog::DIRECTION_END
                && $currentStatus !== null
                && (int) $currentStatus->attendance_status_type_id === (int) $log->attendance_status_type_id
            ) {
                $currentStatus = null;
                $statusSince = $log->event_time;
            }
        }

        $attendanceLabel = $currentStatus?->statusType?->label ?? 'Available';
        $attendanceCode = $currentStatus?->statusType?->id
            ? 'status-'.$currentStatus->statusType->id
            : 'available';

        return [
            'user_id' => $login->user_id,
            'username' => $login->user?->username,
            'name' => $login->user?->full_name ?: ($login->user?->username ?? 'User #'.$login->user_id),
            'role' => $login->user?->role,
            'session_id' => 'A-'.$login->id,
            'status' => 'ONLINE',
            'attendance' => $attendanceLabel,
            'attendance_code' => $attendanceCode,
            'login_at' => $login->event_time?->toIso8601String(),
            'status_since' => $statusSince?->toIso8601String(),
            'session_duration_seconds' => $login->event_time
                ? max(0, (int) $login->event_time->diffInSeconds($now))
                : 0,
            'status_duration_seconds' => $statusSince
                ? max(0, (int) $statusSince->diffInSeconds($now))
                : 0,
            'ip_address' => $login->ip_address,
        ];
    }
}
