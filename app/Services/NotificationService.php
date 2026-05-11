<?php

namespace App\Services;

use App\Models\CrmCallHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationService
{
    private const READ_IDS_TTL_DAYS = 90;

    private const READ_IDS_MAX = 2000;

    public function getForUser(User $user, int $limit = 25): Collection
    {
        $campaign = session('campaign', 'mbsales');
        $aliases = $this->agentAliases($user);
        if ($aliases === []) {
            return collect();
        }

        return CrmCallHistory::query()
            ->where('campaign_code', $campaign)
            ->where(function ($q) use ($aliases): void {
                foreach ($aliases as $i => $alias) {
                    if ($i === 0) {
                        $q->where('agent', $alias);
                    } else {
                        $q->orWhere('agent', $alias);
                    }
                }
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
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
        $merged = array_values(array_unique(array_merge($this->getReadIds($user), $ids)));
        if (count($merged) > self::READ_IDS_MAX) {
            $merged = array_slice($merged, -self::READ_IDS_MAX);
        }
        Cache::put(
            $this->readIdsCacheKey($user),
            $merged,
            now()->addDays(self::READ_IDS_TTL_DAYS),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     source: string,
     *     title: string,
     *     message: string,
     *     time: string,
     *     created_at: string|null,
     *     type: string,
     *     read: bool,
     *     campaign_code: string,
     *     form_type: string,
     *     status: string|null,
     * }
     */
    public function formatHistoryRow(CrmCallHistory $row, bool $read): array
    {
        $formLabel = Str::headline(str_replace(['_', '-'], ' ', (string) $row->form_type));

        $parts = [];
        if ($row->lead_id !== null) {
            $parts[] = 'Lead #'.$row->lead_id;
        }
        if ($row->phone_number !== null && $row->phone_number !== '') {
            $parts[] = $row->phone_number;
        }
        if ($row->record_id !== null) {
            $parts[] = 'Record #'.$row->record_id;
        }
        if ($row->remarks !== null && $row->remarks !== '') {
            $parts[] = Str::limit((string) $row->remarks, 120);
        }

        $status = $row->status !== null && $row->status !== '' ? (string) $row->status : null;
        if ($status !== null) {
            $parts[] = $status;
        }

        $message = $parts !== [] ? implode(' · ', $parts) : 'Form activity recorded for your campaign.';

        return [
            'id' => (int) $row->id,
            'source' => 'Call & form history',
            'title' => $formLabel.' · '.$row->campaign_code,
            'message' => $message,
            'time' => $row->created_at?->diffForHumans() ?? '',
            'created_at' => $row->created_at?->toIso8601String(),
            'type' => $this->inferNotificationType($status),
            'read' => $read,
            'campaign_code' => (string) $row->campaign_code,
            'form_type' => (string) $row->form_type,
            'status' => $status,
        ];
    }

    /**
     * @return list<string>
     */
    private function agentAliases(User $user): array
    {
        $candidates = array_filter(array_map('trim', [
            $user->full_name,
            $user->name,
            $user->username,
            $user->vici_user,
        ]), static fn ($v) => is_string($v) && $v !== '');

        return array_values(array_unique($candidates));
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
