<?php

namespace App\Services\Notifications;

use App\Models\AttendanceLog;
use App\Models\CrmCallHistory;
use App\Models\NotificationReadState;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationReadService
{
    /**
     * @param  list<string>  $keys
     * @return array<string, bool>
     */
    public function states(User $user, array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn (mixed $key): bool => is_string($key) && $key !== '')));
        if ($keys === []) {
            return [];
        }

        return NotificationReadState::query()
            ->forUser((int) $user->id)
            ->whereIn('item_key', $keys)
            ->whereNotNull('read_at')
            ->pluck('read_at', 'item_key')
            ->mapWithKeys(static fn (mixed $value, string $key): array => [$key => true])
            ->all();
    }

    public function markRead(User $user, string $key): bool
    {
        if ($key === '' || ! $this->canRead($user, $key)) {
            return false;
        }

        if (str_starts_with($key, 'database:')) {
            $id = substr($key, strlen('database:'));
            $notification = $user->notifications()->whereKey($id)->first();
            if (! $notification) {
                return false;
            }
            if (! $notification->read()) {
                $notification->markAsRead();
            }

            return true;
        }

        NotificationReadState::query()->updateOrCreate(
            ['user_id' => $user->id, 'item_key' => $key],
            ['read_at' => now()],
        );

        return true;
    }

    public function canRead(User $user, string $key): bool
    {
        if (str_starts_with($key, 'database:')) {
            return $user->notifications()
                ->where('created_at', '>=', now()->subDays((int) config('notifications.activity_days', 30)))
                ->whereKey(substr($key, strlen('database:')))
                ->exists();
        }

        [$prefix, $value] = array_pad(explode(':', $key, 2), 2, null);
        if ($prefix === 'attendance' && ctype_digit((string) $value)) {
            return AttendanceLog::query()
                ->whereKey((int) $value)
                ->where('user_id', $user->id)
                ->where('event_time', '>=', now()->subDays((int) config('notifications.activity_days', 30)))
                ->exists();
        }

        if ($prefix === 'history' && ctype_digit((string) $value)) {
            $aliases = array_values(array_filter(array_map(
                static fn (mixed $alias): string => trim((string) $alias),
                [$user->full_name, $user->name, $user->username, $user->vici_user],
            )));
            if ($aliases === []) {
                return false;
            }

            return CrmCallHistory::query()
                ->whereKey((int) $value)
                ->where('campaign_code', (string) session('campaign', 'mbsales'))
                ->where('created_at', '>=', now()->subDays((int) config('notifications.activity_days', 30)))
                ->where(function ($query) use ($aliases): void {
                    foreach ($aliases as $alias) {
                        $query->orWhereRaw('LOWER(agent) = ?', [strtolower($alias)]);
                    }
                })
                ->exists();
        }

        if ($prefix === 'daily' && is_string($value)) {
            [$encodedCampaign, $date] = array_pad(explode(':', $value, 2), 2, null);

            return rawurldecode((string) $encodedCampaign) === (string) session('campaign', 'mbsales')
                && $date === now(config('app.timezone'))->toDateString();
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     */
    public function markMany(User $user, array $keys): void
    {
        foreach (array_values(array_unique($keys)) as $key) {
            if (is_string($key)) {
                $this->markRead($user, $key);
            }
        }
    }

    /**
     * @param  Collection<int, NotificationItem|array<string, mixed>>  $items
     */
    public function markAll(User $user, Collection $items): void
    {
        $databaseIds = [];
        $derivedRows = [];
        $now = now();

        foreach ($items as $item) {
            $key = is_array($item) ? (string) ($item['key'] ?? '') : $item->key;
            if ($key === '' || ! $this->canRead($user, $key)) {
                continue;
            }
            if (str_starts_with($key, 'database:')) {
                $databaseIds[] = substr($key, strlen('database:'));
            } elseif (in_array(explode(':', $key, 2)[0], ['history', 'attendance', 'daily'], true)) {
                $derivedRows[] = [
                    'user_id' => $user->id,
                    'item_key' => $key,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($databaseIds !== []) {
            $user->unreadNotifications()->whereIn('id', $databaseIds)->update(['read_at' => $now]);
        }
        if ($derivedRows !== []) {
            NotificationReadState::query()->upsert(
                $derivedRows,
                ['user_id', 'item_key'],
                ['read_at', 'updated_at'],
            );
        }
    }

    public function unreadDatabaseCount(User $user, ?string $since = null): int
    {
        $query = $user->unreadNotifications();
        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return (int) $query->count();
    }

    public function prune(int $days = 90): int
    {
        return NotificationReadState::query()
            ->where('read_at', '<', now()->subDays($days))
            ->delete();
    }
}
