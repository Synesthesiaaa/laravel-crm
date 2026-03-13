<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Repositories\VicidialServerRepository;
use App\Support\OperationResult;
use Illuminate\Support\Facades\Http;

class VicidialNonAgentApiService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger
    ) {}

    public function execute(
        User $user,
        string $campaign,
        string $function,
        array $params = [],
        bool $useServerCredentials = true
    ): OperationResult {
        $server = $this->serverRepository->getForCampaign($campaign);
        if (! $server) {
            return OperationResult::failure('No VICIdial server configured for this campaign.');
        }

        $baseUrl = $this->resolveNonAgentUrl((string) ($server->api_url ?? ''));
        if ($baseUrl === '') {
            return OperationResult::failure('Non-Agent API URL is not configured.');
        }

        $apiUser = $useServerCredentials ? (string) ($server->api_user ?? '') : (string) ($user->vici_user ?? '');
        $apiPass = $useServerCredentials ? (string) ($server->api_pass ?? '') : (string) ($user->vici_pass ?? '');

        if ($apiUser === '' || $apiPass === '') {
            return OperationResult::failure('Missing VICIdial API credentials for Non-Agent API.');
        }

        $query = array_merge([
            'function' => $function,
            'source' => (string) ($server->source ?: config('vicidial.default_source', 'crm_tracker')),
            'user' => $apiUser,
            'pass' => $apiPass,
        ], $params);

        try {
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->connectTimeout((int) config('vicidial.connect_timeout', 5))
                ->timeout((int) config('vicidial.timeout', 10))
                ->retry(
                    (int) config('vicidial.retry_times', 2),
                    (int) config('vicidial.retry_sleep_ms', 500)
                )
                ->get($baseUrl, $query);
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('VicidialNonAgentApiService', 'HTTP request failed', [
                'campaign' => $campaign,
                'function' => $function,
                'error' => $e->getMessage(),
            ]);

            return OperationResult::failure('Unable to reach VICIdial Non-Agent API.');
        }

        $body = trim((string) $response->body());
        $normalized = strtolower($body);
        $isError = str_starts_with($normalized, 'error:');
        $isNotice = str_starts_with($normalized, 'notice:');

        if ($isError) {
            $this->telephonyLogger->warning('VicidialNonAgentApiService', 'Non-Agent API returned error', [
                'campaign' => $campaign,
                'function' => $function,
                'response' => strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body,
            ]);

            return OperationResult::failure($body);
        }

        return OperationResult::success([
            'raw_response' => $body,
            'is_notice' => $isNotice,
            'rows' => $this->parseDelimitedRows($body),
        ]);
    }

    protected function resolveNonAgentUrl(string $apiUrl): string
    {
        $configured = trim((string) config('vicidial.non_agent_api_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        $apiUrl = trim($apiUrl);
        if ($apiUrl === '') {
            return '';
        }

        if (str_contains($apiUrl, 'non_agent_api.php')) {
            return $apiUrl;
        }

        if (str_contains($apiUrl, 'agc/api.php')) {
            return preg_replace('#agc/api\.php.*$#', 'non_agent_api.php', $apiUrl) ?: '';
        }

        return rtrim($apiUrl, '/') . '/non_agent_api.php';
    }

    /**
     * Expose the VicidialServer record for a campaign so other services (e.g.
     * VicidialSessionService) can build URLs without duplicating repo logic.
     */
    public function getServerForCampaign(string $campaign): ?\App\Models\VicidialServer
    {
        return $this->serverRepository->getForCampaign($campaign);
    }

    /**
     * Parse common delimited VICIdial rows to structured arrays for UI consumption.
     *
     * @return array<int, array<int, string>>
     */
    protected function parseDelimitedRows(string $body): array
    {
        $rows = [];
        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with(strtolower($line), 'success:') || str_starts_with(strtolower($line), 'notice:')) {
                continue;
            }
            if (str_contains($line, '|')) {
                $rows[] = array_map('trim', explode('|', $line));
            } elseif (str_contains($line, ',')) {
                $rows[] = array_map('trim', explode(',', $line));
            }
        }

        return $rows;
    }
}
