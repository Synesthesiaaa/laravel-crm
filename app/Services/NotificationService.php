<?php

namespace App\Services;

use App\Models\CrmCallHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function getForUser(User $user, int $limit = 25): Collection
    {
        $campaign = session('campaign', 'mbsales');
        $agentName = $user->full_name ?? $user->name ?? $user->username ?? '';
        $q = CrmCallHistory::where('campaign_code', $campaign)
            ->where('agent', $agentName)
            ->orderByDesc('created_at')
            ->limit($limit);

        return $q->get();
    }
}
