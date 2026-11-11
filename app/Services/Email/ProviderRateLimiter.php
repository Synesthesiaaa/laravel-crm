<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\DB;

class ProviderRateLimiter
{
    public function reserve(array $snapshot): int
    {
        $transport = $snapshot['transport'] ?? [];
        $key = hash('sha256', json_encode([$snapshot['provider'] ?? 'smtp', $transport['host'] ?? $transport['transport'] ?? '', $transport['port'] ?? '', $transport['username'] ?? '']));
        $window = intdiv(now()->timestamp, 60);
        $limit = max(1, min((int) ($snapshot['rate_per_minute'] ?? 60), (int) config('email_campaigns.max_rate_per_minute', 10000)));

        return DB::transaction(function () use ($key, $window, $limit): int {
            DB::table('email_provider_limits')->insertOrIgnore(['account_key' => $key, 'window' => $window, 'used' => 0]);
            $row = DB::table('email_provider_limits')->where('account_key', $key)->lockForUpdate()->first();
            $used = (int) $row->window === $window ? (int) $row->used : 0;
            if ($used >= $limit) {
                return max(1, 60 - (now()->timestamp % 60));
            }
            DB::table('email_provider_limits')->where('account_key', $key)->update(['window' => $window, 'used' => $used + 1]);

            return 0;
        });
    }
}
