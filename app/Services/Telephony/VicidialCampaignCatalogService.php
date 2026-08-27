<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Models\VicidialServer;
use App\Support\OperationResult;
use Illuminate\Support\Facades\Cache;

final class VicidialCampaignCatalogService
{
    public function __construct(
        protected VicidialNonAgentApiService $nonAgentApi,
    ) {}

    public function forServer(User $user, VicidialServer $server, bool $forceRefresh = false): OperationResult
    {
        $cacheKey = 'vicidial:campaign-catalog:'.$server->getKey();
        $cacheSeconds = max(0, (int) config('vicidial.campaign_catalog_cache_seconds', 60));
        if (! $forceRefresh && $cacheSeconds > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return OperationResult::success(['campaigns' => $cached], null, [
                    'campaign' => $server->campaign_code,
                    'server_id' => $server->getKey(),
                    'classification' => 'CACHE',
                ]);
            }
        }

        $result = $this->nonAgentApi->executeOnServer(
            $user,
            $server,
            (string) $server->campaign_code,
            'campaigns_list',
            ['stage' => 'pipe', 'header' => 'YES'],
            true,
            ['connect_timeout' => 3, 'timeout' => 10, 'retry_times' => 1],
        );
        if (! $result->success) {
            return $result;
        }

        $campaigns = $this->parse($result);
        if ($cacheSeconds > 0) {
            Cache::put($cacheKey, $campaigns, now()->addSeconds($cacheSeconds));
        }

        return OperationResult::success(['campaigns' => $campaigns], null, $result->meta);
    }

    public function clear(VicidialServer $server): void
    {
        Cache::forget('vicidial:campaign-catalog:'.$server->getKey());
    }

    /**
     * @param  array<int, string>  $codes
     */
    public function validateCodes(User $user, VicidialServer $server, array $codes): OperationResult
    {
        $result = $this->forServer($user, $server);
        if (! $result->success) {
            return $result;
        }

        $available = collect((array) ($result->data['campaigns'] ?? []))
            ->pluck('code')
            ->map(fn (mixed $code): string => strtolower(trim((string) $code)))
            ->all();
        $missing = array_values(array_filter($codes, fn (string $code): bool => ! in_array(strtolower(trim($code)), $available, true)));
        if ($missing !== []) {
            return OperationResult::failure(
                'One or more selected VICIdial campaigns are not available on the selected server: '.implode(', ', $missing),
                null,
                ['classification' => 'CAMPAIGN_NOT_FOUND', 'missing_codes' => $missing],
            );
        }

        return OperationResult::success(['campaigns' => $result->data['campaigns'] ?? []], null, $result->meta);
    }

    /**
     * @return array<int, array{code: string, name: string, is_active: bool}>
     */
    private function parse(OperationResult $result): array
    {
        $rows = array_values(array_filter((array) ($result->data['rows'] ?? []), 'is_array'));
        if ($rows === []) {
            return [];
        }

        $headers = array_map(fn (mixed $header): string => $this->key($header), $rows[0]);
        $campaigns = [];
        foreach (array_slice($rows, 1) as $row) {
            $values = [];
            foreach ($headers as $index => $header) {
                $values[$header] = trim((string) ($row[$index] ?? ''));
            }
            $code = trim((string) ($values['campaign_id'] ?? $values['campaign'] ?? $values['campaign_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $campaigns[] = [
                'code' => $code,
                'name' => trim((string) ($values['campaign_name'] ?? $values['name'] ?? $code)),
                'is_active' => strtoupper((string) ($values['active'] ?? 'Y')) === 'Y',
            ];
        }

        return collect($campaigns)->unique(fn (array $campaign): string => strtolower($campaign['code']))->values()->all();
    }

    private function key(mixed $value): string
    {
        return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $value), '_'));
    }
}
