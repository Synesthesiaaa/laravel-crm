<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\CrmCallHistory;
use App\Models\User;
use App\Services\Notifications\DailyPerformanceNotificationProvider;
use App\Services\Notifications\NotificationLabelResolver;
use App\Services\Notifications\NotificationReadService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationService
{
    private const READ_IDS_TTL_DAYS = 90;

    private const READ_IDS_MAX = 2000;

    public function __construct(
        protected NotificationReadService $readService,
        protected NotificationLabelResolver $labels,
        protected DailyPerformanceNotificationProvider $dailyPerformance,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, unread: int, has_more: bool, refreshed_at: string}
     */
    public function getFeedForUser(User $user, int $limit = 25): array
    {
        $limit = max(1, min($limit, (int) config('notifications.visible_limit', 25)));
        $cutoff = now()->subDays((int) config('notifications.activity_days', 30));
        $sourceLimit = max($limit + 1, max(1, (int) config('notifications.source_query_limit', 1000)));
        $campaign = (string) session('campaign', 'mbsales');

        $database = $this->getDatabaseForUser($user, $sourceLimit, $cutoff)
            ->map(fn (DatabaseNotification $notification): array => $this->formatDatabaseNotification($notification));
        $history = $this->getForUser($user, $sourceLimit, $cutoff);
        $attendance = AttendanceLog::query()
            ->forUser((int) $user->id)
            ->where('event_time', '>=', $cutoff)
            ->with('statusType')
            ->orderByDesc('event_time')
            ->orderByDesc('id')
            ->limit($sourceLimit)
            ->get()
            ->map(fn (AttendanceLog $log): array => $this->formatAttendanceRow($log));
        $daily = $this->dailyPerformance->item($user, $campaign);

        $derivedKeys = $history->map(fn (CrmCallHistory $row): string => 'history:'.$row->id)
            ->concat($attendance->map(fn (array $row): string => $row['key']))
            ->push($daily->key)
            ->all();
        $readStates = $this->readService->states($user, $derivedKeys);
        foreach ($this->getReadIds($user) as $legacyId) {
            $legacyKey = 'history:'.$legacyId;
            if (in_array($legacyKey, $derivedKeys, true)) {
                $readStates[$legacyKey] = true;
            }
        }

        $historyItems = $history->map(function (CrmCallHistory $row) use ($readStates): array {
            $key = 'history:'.$row->id;

            return $this->formatHistoryRow($row, isset($readStates[$key]));
        });
        $attendanceItems = $attendance->map(function (array $row) use ($readStates): array {
            $row['read'] = isset($readStates[$row['key']]);

            return $row;
        });
        $dailyItem = $daily->withRead(isset($readStates[$daily->key]))->toArray();

        $items = $database
            ->concat($historyItems)
            ->concat($attendanceItems)
            ->push($dailyItem)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['key']))
            ->unique('key')
            ->sort(function (array $left, array $right): int {
                $leftTime = (string) ($left['created_at'] ?? '');
                $rightTime = (string) ($right['created_at'] ?? '');
                $leftTimestamp = $leftTime === '' ? PHP_INT_MIN : Carbon::parse($leftTime)->getTimestamp();
                $rightTimestamp = $rightTime === '' ? PHP_INT_MIN : Carbon::parse($rightTime)->getTimestamp();
                $comparison = $rightTimestamp <=> $leftTimestamp;

                return $comparison !== 0 ? $comparison : strcmp((string) $left['key'], (string) $right['key']);
            })
            ->values();

        $hasMore = $items->count() > $limit;
        $visible = $items->take($limit)->values();
        $summary = $this->getSummaryForUser($user);

        return [
            'items' => $visible->all(),
            'unread' => $summary['unread'],
            'has_more' => $hasMore,
            'refreshed_at' => now(config('app.timezone'))->toIso8601String(),
        ];
    }

    /**
     * @return array{unread: int, refreshed_at: string}
     */
    public function getSummaryForUser(User $user): array
    {
        $cutoff = now()->subDays((int) config('notifications.activity_days', 30));
        $campaign = (string) session('campaign', 'mbsales');
        $sourceLimit = max(1, (int) config('notifications.source_query_limit', 1000));
        $historyKeys = $this->historyQuery($user, $campaign, $cutoff)
            ->limit($sourceLimit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => 'history:'.$id)
            ->all();
        $attendanceKeys = AttendanceLog::query()
            ->forUser((int) $user->id)
            ->where('event_time', '>=', $cutoff)
            ->limit($sourceLimit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => 'attendance:'.$id)
            ->all();
        $dailyKey = $this->dailyPerformance->key($campaign, now(config('app.timezone'))->toDateString());
        $readStates = $this->readService->states($user, [...$historyKeys, ...$attendanceKeys, $dailyKey]);
        foreach ($this->getReadIds($user) as $legacyId) {
            $legacyKey = 'history:'.$legacyId;
            if (in_array($legacyKey, $historyKeys, true)) {
                $readStates[$legacyKey] = true;
            }
        }
        $derivedUnread = count($historyKeys) + count($attendanceKeys) + 1 - count($readStates);

        return [
            'unread' => max(0, $this->readService->unreadDatabaseCount($user, $cutoff->toDateTimeString()) + $derivedUnread),
            'refreshed_at' => now(config('app.timezone'))->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, CrmCallHistory>
     */
    public function getForUser(User $user, int $limit = 25, ?CarbonInterface $cutoff = null): Collection
    {
        $campaign = (string) session('campaign', 'mbsales');
        $aliases = $this->labels->aliases($user);
        if ($aliases === []) {
            return collect();
        }

        $query = $this->historyQuery($user, $campaign, $cutoff);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function historyQuery(User $user, string $campaign, ?CarbonInterface $cutoff = null): Builder
    {
        $aliases = $this->labels->aliases($user);
        $query = CrmCallHistory::query()->where('campaign_code', $campaign);
        if ($aliases === []) {
            return $query->whereRaw('1 = 0');
        }
        $query->where(function ($q) use ($aliases): void {
            foreach ($aliases as $alias) {
                $q->orWhereRaw('LOWER(agent) = ?', [strtolower($alias)]);
            }
        });
        if ($cutoff !== null) {
            $query->where('created_at', '>=', $cutoff);
        }

        return $query;
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function getDatabaseForUser(User $user, int $limit = 25, ?CarbonInterface $cutoff = null): Collection
    {
        $query = $user->notifications();
        if ($cutoff !== null) {
            $query->where('created_at', '>=', $cutoff);
        }

        return $query
            ->latest()
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Resolve a stable key while re-applying ownership and active-campaign scope.
     *
     * @return array<string, mixed>|null
     */
    public function getDetailForUser(User $user, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        if (str_starts_with($key, 'database:')) {
            $id = substr($key, strlen('database:'));
            $notification = $user->notifications()
                ->where('created_at', '>=', now()->subDays((int) config('notifications.activity_days', 30)))
                ->whereKey($id)
                ->first();

            return $notification ? $this->formatDatabaseDetail($notification) : null;
        }

        [$prefix, $value] = array_pad(explode(':', $key, 2), 2, null);
        if ($prefix === 'history' && ctype_digit((string) $value)) {
            $row = $this->historyQuery(
                $user,
                (string) session('campaign', 'mbsales'),
                now()->subDays((int) config('notifications.activity_days', 30)),
            )->whereKey((int) $value)->first();
            if (! $row) {
                return null;
            }

            return $this->formatHistoryDetail($row);
        }

        if ($prefix === 'attendance' && ctype_digit((string) $value)) {
            $log = AttendanceLog::query()
                ->forUser((int) $user->id)
                ->where('event_time', '>=', now()->subDays((int) config('notifications.activity_days', 30)))
                ->with('statusType')
                ->find((int) $value);
            if (! $log) {
                return null;
            }

            return $this->formatAttendanceDetail($log);
        }

        if ($prefix === 'daily' && is_string($value)) {
            [$encodedCampaign, $date] = array_pad(explode(':', $value, 2), 2, null);
            $campaign = rawurldecode((string) $encodedCampaign);
            $activeCampaign = (string) session('campaign', 'mbsales');
            $today = now(config('app.timezone'))->toDateString();
            if ($campaign === '' || $date !== $today || $campaign !== $activeCampaign) {
                return null;
            }

            $detail = $this->dailyPerformance->details($user, $campaign, $date);

            return $detail === [] ? null : $detail;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function getReadIds(User $user): array
    {
        $raw = Cache::get($this->readIdsCacheKey($user), []);

        return array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function markIdsRead(User $user, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $this->readService->markMany($user, array_map(static fn (int $id): string => 'history:'.$id, $ids));
        $merged = array_values(array_unique(array_merge($this->getReadIds($user), $ids)));
        if (count($merged) > self::READ_IDS_MAX) {
            $merged = array_slice($merged, -self::READ_IDS_MAX);
        }
        Cache::put($this->readIdsCacheKey($user), $merged, now()->addDays(self::READ_IDS_TTL_DAYS));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatHistoryRow(CrmCallHistory $row, bool $read): array
    {
        $formLabel = $this->labels->form((string) $row->form_type, (string) $row->campaign_code);
        $campaignLabel = $this->labels->campaign((string) $row->campaign_code);
        $status = $row->status !== null && trim((string) $row->status) !== ''
            ? $this->labels->status((string) $row->status)
            : null;
        $parts = [];
        if ($row->lead_id !== null) {
            $parts[] = 'Lead #'.$row->lead_id;
        }
        if ($row->phone_number !== null && $row->phone_number !== '') {
            $parts[] = (string) $row->phone_number;
        }
        if ($row->record_id !== null) {
            $parts[] = 'Record #'.$row->record_id;
        }
        if ($row->remarks !== null && trim((string) $row->remarks) !== '') {
            $parts[] = Str::limit((string) $row->remarks, 120);
        }
        if ($status !== null) {
            $parts[] = $status;
        }

        $message = $parts !== [] ? implode(' · ', $parts) : 'Form activity recorded for your campaign.';

        return [
            'id' => 'history:'.$row->id,
            'key' => 'history:'.$row->id,
            'category' => 'call_form',
            'source' => 'Call & form history',
            'title' => $formLabel,
            'message' => $message,
            'time' => $row->created_at?->diffForHumans() ?? '',
            'created_at' => $row->created_at?->toIso8601String(),
            'type' => $this->inferNotificationType($status),
            'read' => $read,
            'detail_available' => true,
            'preview' => [
                'campaign' => $campaignLabel,
                'form' => $formLabel,
                ...($status === null ? [] : ['status' => $status]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAttendanceRow(AttendanceLog $log): array
    {
        $status = $this->labels->status($log->event_type, $log->attendance_status_type_id);
        $action = $this->labels->attendanceAction($log->direction);

        return [
            'id' => 'attendance:'.$log->id,
            'key' => 'attendance:'.$log->id,
            'category' => 'attendance',
            'source' => 'Attendance',
            'title' => $status,
            'message' => $action.' attendance status',
            'time' => $log->event_time?->diffForHumans() ?? '',
            'created_at' => $log->event_time?->toIso8601String(),
            'type' => 'info',
            'read' => false,
            'detail_available' => true,
            'preview' => ['status' => $status, 'action' => $action],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDatabaseNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $createdAt = $notification->created_at;

        return [
            'id' => 'database:'.$notification->id,
            'key' => 'database:'.$notification->id,
            'category' => 'supervisor',
            'source' => (string) ($data['source'] ?? 'Notification'),
            'title' => (string) ($data['title'] ?? 'Update'),
            'message' => (string) ($data['message'] ?? ''),
            'time' => $createdAt?->diffForHumans() ?? '',
            'created_at' => $createdAt?->toIso8601String(),
            'type' => (string) ($data['type'] ?? 'info'),
            'read' => $notification->read(),
            'detail_available' => true,
            'preview' => [
                'recipient' => $this->labels->recipient(isset($data['recipient_type']) ? (string) $data['recipient_type'] : null),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDatabaseDetail(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $sender = isset($data['sender_id']) ? $this->labels->user((int) $data['sender_id']) : 'Supervisor';

        return [
            'key' => 'database:'.$notification->id,
            'category' => 'supervisor',
            'title' => (string) ($data['title'] ?? 'Update'),
            'description' => 'Message from '.$sender.'.',
            'sections' => [[
                'title' => 'Supervisor message',
                'metrics' => [
                    ['label' => 'From', 'value' => $sender],
                    ['label' => 'Recipient', 'value' => $this->labels->recipient(isset($data['recipient_type']) ? (string) $data['recipient_type'] : null)],
                    ['label' => 'Sent', 'value' => $notification->created_at?->format('M j, Y g:i A') ?? 'Unknown time'],
                ],
                'message' => (string) ($data['message'] ?? ''),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHistoryDetail(CrmCallHistory $row): array
    {
        $formatted = $this->formatHistoryRow($row, true);
        $metrics = [
            ['label' => 'Campaign', 'value' => $this->labels->campaign((string) $row->campaign_code)],
            ['label' => 'Form', 'value' => $this->labels->form((string) $row->form_type, (string) $row->campaign_code)],
            ['label' => 'Recorded', 'value' => $row->created_at?->format('M j, Y g:i A') ?? 'Unknown time'],
        ];
        if ($row->status !== null && trim((string) $row->status) !== '') {
            $metrics[] = ['label' => 'Status', 'value' => $this->labels->status((string) $row->status)];
        }

        $fields = [];
        if ($row->lead_id !== null) {
            $fields[] = ['label' => 'Lead', 'value' => '#'.$row->lead_id];
        }
        if ($row->record_id !== null) {
            $fields[] = ['label' => 'Record', 'value' => '#'.$row->record_id];
        }
        if ($row->phone_number !== null && $row->phone_number !== '') {
            $fields[] = ['label' => 'Phone', 'value' => (string) $row->phone_number];
        }

        return [
            'key' => $formatted['key'],
            'category' => 'call_form',
            'title' => $formatted['title'],
            'description' => 'Call and form activity for your campaign.',
            'sections' => [[
                'title' => 'Activity',
                'metrics' => $metrics,
                'fields' => $fields,
                ...($row->remarks === null || trim((string) $row->remarks) === ''
                    ? []
                    : ['message' => (string) $row->remarks]),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAttendanceDetail(AttendanceLog $log): array
    {
        $status = $this->labels->status($log->event_type, $log->attendance_status_type_id);
        $action = $this->labels->attendanceAction($log->direction);
        $metrics = [
            ['label' => 'Status', 'value' => $status],
            ['label' => 'Action', 'value' => $action],
            ['label' => 'Recorded', 'value' => $log->event_time?->format('M j, Y g:i A') ?? 'Unknown time'],
        ];
        $paired = $this->pairedAttendanceLog($log);
        if ($paired !== null) {
            $metrics[] = [
                'label' => $log->direction === AttendanceLog::DIRECTION_START ? 'Ended' : 'Started',
                'value' => $paired->event_time?->format('M j, Y g:i A') ?? 'Unknown time',
            ];
            if ($log->direction === AttendanceLog::DIRECTION_END && $log->event_time && $paired->event_time) {
                $metrics[] = ['label' => 'Duration', 'value' => $paired->event_time->diffForHumans($log->event_time, true)];
            }
        } elseif ($log->direction === AttendanceLog::DIRECTION_START) {
            $metrics[] = ['label' => 'State', 'value' => 'Open'];
        }

        return [
            'key' => 'attendance:'.$log->id,
            'category' => 'attendance',
            'title' => $status,
            'description' => 'Your attendance activity.',
            'sections' => [['title' => 'Attendance', 'metrics' => $metrics]],
        ];
    }

    private function pairedAttendanceLog(AttendanceLog $log): ?AttendanceLog
    {
        if ($log->attendance_status_type_id === null || $log->direction === null) {
            return null;
        }

        $query = AttendanceLog::query()
            ->where('user_id', $log->user_id)
            ->where('attendance_status_type_id', $log->attendance_status_type_id)
            ->whereDate('event_time', $log->event_time?->toDateString());
        if ($log->direction === AttendanceLog::DIRECTION_START) {
            return $query->where('direction', AttendanceLog::DIRECTION_END)
                ->where('event_time', '>', $log->event_time)
                ->orderBy('event_time')
                ->first();
        }

        return $query->where('direction', AttendanceLog::DIRECTION_START)
            ->where('event_time', '<', $log->event_time)
            ->orderByDesc('event_time')
            ->first();
    }

    /**
     * @return list<string>
     */
    public function visibleKeys(User $user): array
    {
        return collect($this->getFeedForUser($user)['items'])
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();
    }

    private function readIdsCacheKey(User $user): string
    {
        return 'crm_notification_read_ids_'.$user->id;
    }

    private function inferNotificationType(?string $status): string
    {
        if ($status === null) {
            return 'info';
        }
        $u = strtoupper($status);
        if (str_contains($u, 'FAIL') || str_contains($u, 'ERROR') || str_contains($u, 'REJECT')) {
            return 'error';
        }
        if (str_contains($u, 'PENDING') || str_contains($u, 'WARN')) {
            return 'warning';
        }
        if ($u === 'RECORDED' || str_contains($u, 'SUCCESS') || str_contains($u, 'OK')) {
            return 'success';
        }

        return 'info';
    }
}
