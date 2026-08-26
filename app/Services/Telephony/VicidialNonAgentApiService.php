<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Repositories\VicidialServerRepository;
use App\Support\OperationResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class VicidialNonAgentApiService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    public function execute(
        User $user,
        string $campaign,
        string $function,
        array $params = [],
        bool $useServerCredentials = true,
        array $httpOptions = [],
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
                ->connectTimeout((int) ($httpOptions['connect_timeout'] ?? config('vicidial.connect_timeout', 5)))
                ->timeout((int) ($httpOptions['timeout'] ?? config('vicidial.timeout', 10)))
                ->retry(
                    (int) ($httpOptions['retry_times'] ?? config('vicidial.retry_times', 2)),
                    (int) ($httpOptions['retry_sleep_ms'] ?? config('vicidial.retry_sleep_ms', 500)),
                )
                ->get($baseUrl, $query);
        } catch (Throwable $e) {
            $this->telephonyLogger->error('VicidialNonAgentApiService', 'HTTP request failed', [
                'campaign' => $campaign,
                'function' => $function,
                'error' => $this->redactConnectionExceptionMessage($e->getMessage()),
            ]);

            return OperationResult::failure('Unable to reach VICIdial Non-Agent API.');
        }

        return $this->responseResult($response, $campaign, $function);
    }

    /**
     * Execute independent Non-Agent API functions concurrently against the
     * single VICIdial server assigned to a CRM campaign.
     *
     * @param  array<string, array{function: string, params?: array<string, mixed>}>  $requests
     * @param  array<string, int>  $httpOptions
     * @return array<string, OperationResult>
     */
    public function executeBatch(
        User $user,
        string $campaign,
        array $requests,
        bool $useServerCredentials = true,
        array $httpOptions = [],
    ): array {
        if ($requests === []) {
            return [];
        }

        $server = $this->serverRepository->getForCampaign($campaign);
        if (! $server) {
            return $this->failedBatch($requests, 'No VICIdial server configured for this campaign.');
        }

        $baseUrl = $this->resolveNonAgentUrl((string) ($server->api_url ?? ''));
        if ($baseUrl === '') {
            return $this->failedBatch($requests, 'Non-Agent API URL is not configured.');
        }

        $apiUser = $useServerCredentials ? (string) ($server->api_user ?? '') : (string) ($user->vici_user ?? '');
        $apiPass = $useServerCredentials ? (string) ($server->api_pass ?? '') : (string) ($user->vici_pass ?? '');
        if ($apiUser === '' || $apiPass === '') {
            return $this->failedBatch($requests, 'Missing VICIdial API credentials for Non-Agent API.');
        }

        $source = (string) ($server->source ?: config('vicidial.default_source', 'crm_tracker'));
        $requestQueries = [];
        foreach ($requests as $key => $definition) {
            $function = trim((string) ($definition['function'] ?? ''));
            if ($function === '') {
                continue;
            }

            $requestQueries[$key] = [
                'function' => $function,
                'query' => array_merge([
                    'function' => $function,
                    'source' => $source,
                    'user' => $apiUser,
                    'pass' => $apiPass,
                ], (array) ($definition['params'] ?? [])),
            ];
        }

        if ($requestQueries === []) {
            return $this->failedBatch($requests, 'No valid VICIdial functions were requested.');
        }

        $connectTimeout = (int) ($httpOptions['connect_timeout'] ?? config('vicidial.connect_timeout', 5));
        $timeout = (int) ($httpOptions['timeout'] ?? config('vicidial.timeout', 10));
        $retryTimes = (int) ($httpOptions['retry_times'] ?? config('vicidial.retry_times', 2));
        $retrySleepMs = (int) ($httpOptions['retry_sleep_ms'] ?? config('vicidial.retry_sleep_ms', 500));

        try {
            $responses = Http::pool(function (Pool $pool) use ($requestQueries, $baseUrl, $connectTimeout, $timeout, $retryTimes, $retrySleepMs): array {
                $pending = [];
                foreach ($requestQueries as $key => $request) {
                    $client = $pool->as((string) $key);
                    if (! config('vicidial.verify_ssl', true)) {
                        $client = $client->withoutVerifying();
                    }

                    $pending[] = $client
                        ->connectTimeout($connectTimeout)
                        ->timeout($timeout)
                        ->retry($retryTimes, $retrySleepMs)
                        ->get($baseUrl, $request['query']);
                }

                return $pending;
            }, concurrency: count($requestQueries));
        } catch (Throwable $e) {
            $this->telephonyLogger->error('VicidialNonAgentApiService', 'HTTP batch failed', [
                'campaign' => $campaign,
                'functions' => array_column($requestQueries, 'function'),
                'error' => $this->redactConnectionExceptionMessage($e->getMessage()),
            ]);

            return $this->failedBatch($requests, 'Unable to reach VICIdial Non-Agent API.');
        }

        $results = [];
        foreach ($requestQueries as $key => $request) {
            $response = $responses[$key] ?? null;
            if (! $response instanceof Response) {
                $this->telephonyLogger->error('VicidialNonAgentApiService', 'HTTP batch request failed', [
                    'campaign' => $campaign,
                    'function' => $request['function'],
                    'error' => $response instanceof Throwable
                        ? $this->redactConnectionExceptionMessage($response->getMessage())
                        : 'No response returned.',
                ]);
                $results[$key] = OperationResult::failure('Unable to reach VICIdial Non-Agent API.');

                continue;
            }

            $results[$key] = $this->responseResult($response, $campaign, $request['function']);
        }

        return $results;
    }

    private function responseResult(Response $response, string $campaign, string $function): OperationResult
    {
        $body = trim((string) $response->body());
        $normalized = strtolower($body);
        $isError = str_starts_with($normalized, 'error:');
        $isNotice = str_starts_with($normalized, 'notice:');

        if (! $response->successful() || $isError) {
            $this->telephonyLogger->warning('VicidialNonAgentApiService', 'Non-Agent API returned error', [
                'campaign' => $campaign,
                'function' => $function,
                'status' => $response->status(),
                'response' => strlen($body) > 500 ? substr($body, 0, 500).'...' : $body,
            ]);

            return OperationResult::failure($body !== '' ? $body : 'VICIdial Non-Agent API request failed.');
        }

        return OperationResult::success([
            'raw_response' => $body,
            'is_notice' => $isNotice,
            'rows' => $this->parseDelimitedRows($body),
        ]);
    }

    /**
     * @param  array<string, mixed>  $requests
     * @return array<string, OperationResult>
     */
    private function failedBatch(array $requests, string $message): array
    {
        $results = [];
        foreach (array_keys($requests) as $key) {
            $results[$key] = OperationResult::failure($message);
        }

        return $results;
    }

    /**
     * cURL includes a request URL in some connection exception messages. The
     * Non-Agent API authenticates with query parameters, so preserve useful
     * diagnostics without persisting API credentials in telemetry.
     */
    protected function redactConnectionExceptionMessage(string $message): string
    {
        return preg_replace('/([?&](?:user|pass)=)[^&#\s]*/i', '$1[redacted]', $message) ?? $message;
    }

    protected function resolveNonAgentUrl(string $apiUrl): string
    {
        $apiUrl = trim($apiUrl);
        if ($apiUrl !== '') {
            if (str_contains($apiUrl, 'non_agent_api.php')) {
                return $apiUrl;
            }

            if (str_contains($apiUrl, 'agc/api.php')) {
                return preg_replace('#agc/api\.php.*$#', 'non_agent_api.php', $apiUrl) ?: '';
            }

            return rtrim($apiUrl, '/').'/non_agent_api.php';
        }

        return trim((string) config('vicidial.non_agent_api_url', ''));
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
