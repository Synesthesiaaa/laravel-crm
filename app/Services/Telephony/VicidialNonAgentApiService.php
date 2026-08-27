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
        ?VicidialEndpointResolver $endpointResolver = null,
    ) {
        $this->endpointResolver = $endpointResolver ?? new VicidialEndpointResolver;
    }

    protected VicidialEndpointResolver $endpointResolver;

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
            return OperationResult::failure('No VICIdial server configured for this campaign.', null, [
                'campaign' => $campaign,
                'endpoint' => 'non_agent_api',
                'classification' => 'NOT_CONFIGURED',
            ]);
        }

        $baseUrl = $this->endpointResolver->nonAgentApi($server);
        if ($baseUrl === '') {
            return OperationResult::failure('Non-Agent API URL is not configured.', null, $this->baseMeta(
                $campaign,
                $server,
                'NOT_CONFIGURED',
            ));
        }

        $apiUser = $useServerCredentials ? (string) ($server->api_user ?? '') : (string) ($user->vici_user ?? '');
        $apiPass = $useServerCredentials ? (string) ($server->api_pass ?? '') : (string) ($user->vici_pass ?? '');

        if ($apiUser === '' || $apiPass === '') {
            return OperationResult::failure('Missing VICIdial API credentials for Non-Agent API.', null, $this->baseMeta(
                $campaign,
                $server,
                'AUTHENTICATION_FAILED',
            ));
        }

        $query = array_merge([
            'function' => $function,
            'source' => (string) ($server->source ?: config('vicidial.default_source', 'crm_tracker')),
            'user' => $apiUser,
            'pass' => $apiPass,
        ], $params);
        $startedAt = microtime(true);

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
            $classification = $this->classifyException($e);
            $meta = array_merge($this->baseMeta($campaign, $server, $classification), [
                'duration_ms' => $this->durationMs($startedAt),
                'parser' => 'not_run',
                'parsed_rows' => 0,
                'error' => $this->redactConnectionExceptionMessage($e->getMessage()),
            ]);
            $this->logRequest($function, $meta, 'HTTP request failed', 'error');

            return OperationResult::failure($this->safeMessage($classification), null, $meta);
        }

        return $this->responseResult($response, $campaign, $function, $server, $startedAt);
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
            return $this->failedBatch($requests, 'No VICIdial server configured for this campaign.', 'NOT_CONFIGURED');
        }

        $baseUrl = $this->endpointResolver->nonAgentApi($server);
        if ($baseUrl === '') {
            return $this->failedBatch($requests, 'Non-Agent API URL is not configured.', 'NOT_CONFIGURED');
        }

        $apiUser = $useServerCredentials ? (string) ($server->api_user ?? '') : (string) ($user->vici_user ?? '');
        $apiPass = $useServerCredentials ? (string) ($server->api_pass ?? '') : (string) ($user->vici_pass ?? '');
        if ($apiUser === '' || $apiPass === '') {
            return $this->failedBatch($requests, 'Missing VICIdial API credentials for Non-Agent API.', 'AUTHENTICATION_FAILED');
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
            return $this->failedBatch($requests, 'No valid VICIdial functions were requested.', 'PARSE_ERROR');
        }

        $connectTimeout = (int) ($httpOptions['connect_timeout'] ?? config('vicidial.connect_timeout', 5));
        $timeout = (int) ($httpOptions['timeout'] ?? config('vicidial.timeout', 10));
        $retryTimes = (int) ($httpOptions['retry_times'] ?? config('vicidial.retry_times', 2));
        $retrySleepMs = (int) ($httpOptions['retry_sleep_ms'] ?? config('vicidial.retry_sleep_ms', 500));

        $batchStartedAt = microtime(true);

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
            $classification = $this->classifyException($e);
            $meta = array_merge($this->baseMeta($campaign, $server, $classification), [
                'functions' => array_column($requestQueries, 'function'),
                'parser' => 'not_run',
                'parsed_rows' => 0,
            ]);
            $this->logRequest('batch', $meta, 'HTTP batch failed');

            return $this->failedBatch($requests, $this->safeMessage($classification), $classification, $meta);
        }

        $results = [];
        foreach ($requestQueries as $key => $request) {
            $response = $responses[$key] ?? null;
            if (! $response instanceof Response) {
                $exception = $response instanceof Throwable ? $response : null;
                $classification = $exception ? $this->classifyException($exception) : 'NETWORK_ERROR';
                $meta = array_merge($this->baseMeta($campaign, $server, $classification), [
                    'parser' => 'not_run',
                    'parsed_rows' => 0,
                ]);
                $this->logRequest($request['function'], $meta, 'HTTP batch request failed');
                $results[$key] = OperationResult::failure($this->safeMessage($classification), null, $meta);

                continue;
            }

            $results[$key] = $this->responseResult(
                $response,
                $campaign,
                $request['function'],
                $server,
                $batchStartedAt,
            );
        }

        return $results;
    }

    private function responseResult(
        Response $response,
        string $campaign,
        string $function,
        \App\Models\VicidialServer $server,
        float $startedAt,
    ): OperationResult {
        $body = trim((string) $response->body());
        $normalized = strtolower($body);
        $isNotice = str_starts_with($normalized, 'notice:');
        $rows = $this->parseDelimitedRows($body);
        $classification = $this->classifyResponse($response, $body, $rows);
        $parsedRows = $classification === 'OK' ? count($rows) : 0;
        $meta = array_merge($this->baseMeta($campaign, $server, $classification), [
            'http_status' => $response->status(),
            'content_type' => (string) ($response->header('Content-Type') ?? ''),
            'response_bytes' => strlen($body),
            'duration_ms' => $this->durationMs($startedAt),
            'parser' => ! in_array($classification, ['OK', 'REPORT_EMPTY'], true)
                ? 'failed'
                : 'delimited_rows',
            'parsed_rows' => $parsedRows,
            'body_sha256' => hash('sha256', $body),
        ]);

        $this->logRequest($function, $meta, $classification === 'OK' || $classification === 'REPORT_EMPTY'
            ? 'Non-Agent API request completed'
            : 'Non-Agent API request failed');

        if ($classification === 'REPORT_EMPTY') {
            return OperationResult::success([
                'raw_response' => $body,
                'is_notice' => $isNotice,
                'rows' => [],
            ], null, $meta);
        }

        if ($classification !== 'OK') {
            return OperationResult::failure($this->safeMessage($classification), null, $meta);
        }

        return OperationResult::success([
            'raw_response' => $body,
            'is_notice' => $isNotice,
            'rows' => $rows,
        ], null, $meta);
    }

    /**
     * @param  array<string, mixed>  $requests
     * @return array<string, OperationResult>
     */
    private function failedBatch(array $requests, string $message, string $classification, array $meta = []): array
    {
        $results = [];
        foreach (array_keys($requests) as $key) {
            $results[$key] = OperationResult::failure($message, null, array_merge($meta, [
                'classification' => $classification,
            ]));
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

    /**
     * Expose the VicidialServer record for a campaign so other services (e.g.
     * VicidialSessionService) can build URLs without duplicating repo logic.
     */
    public function getServerForCampaign(string $campaign): ?\App\Models\VicidialServer
    {
        return $this->serverRepository->getForCampaign($campaign);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(string $campaign, \App\Models\VicidialServer $server, string $classification): array
    {
        return [
            'campaign' => $campaign,
            'server_id' => $server->getKey(),
            'server_name' => (string) $server->server_name,
            'endpoint' => 'non_agent_api',
            'classification' => $classification,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function classifyResponse(Response $response, string $body, array $rows): string
    {
        $normalized = strtolower($body);
        $contentType = strtolower((string) ($response->header('Content-Type') ?? ''));

        if ($response->status() === 401 || $this->containsAny($normalized, [
            'login incorrect',
            'invalid username',
            'invalid password',
            'authentication failed',
            'session expired',
        ])) {
            return 'AUTHENTICATION_FAILED';
        }
        if (str_contains($normalized, '<html') && $this->containsAny($normalized, [
            '<title>login',
            'name="password"',
            'type="password"',
            'sign in',
        ])) {
            return 'AUTHENTICATION_FAILED';
        }
        if ($response->status() === 403 || $this->containsAny($normalized, [
            'permission denied',
            'access denied',
            'not authorized',
            'does not have permission',
            'view reports',
        ])) {
            return 'PERMISSION_DENIED';
        }
        if ($response->status() >= 500) {
            return 'SERVER_ERROR';
        }
        if (! $response->successful()) {
            return $response->status() >= 300 && $response->status() < 400
                ? 'AUTHENTICATION_FAILED'
                : 'SERVER_ERROR';
        }
        if ($body === '' || $normalized === 'success' || $normalized === 'notice' || str_starts_with($normalized, 'success:') || str_starts_with($normalized, 'notice:')) {
            return 'REPORT_EMPTY';
        }
        if ($this->containsAny($normalized, [
            'no logged in agents',
            'no agents logged in',
            'no logged-in agents',
        ])) {
            return 'REPORT_EMPTY';
        }
        if (str_starts_with($normalized, 'error:')) {
            return 'SERVER_ERROR';
        }
        if (str_contains($normalized, '<html') || (str_contains($contentType, 'html') && $rows === [])) {
            return 'REPORT_HTML_CHANGED';
        }
        if ($rows === []) {
            return 'PARSE_ERROR';
        }

        return 'OK';
    }

    private function classifyException(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timed out') || str_contains($message, 'timeout') || str_contains($message, 'error 28')) {
            return 'NETWORK_TIMEOUT';
        }
        if (str_contains($message, 'refused') || str_contains($message, 'error 7')) {
            return 'CONNECTION_REFUSED';
        }
        if (str_contains($message, 'ssl') || str_contains($message, 'certificate') || str_contains($message, 'tls')) {
            return 'SSL_ERROR';
        }

        return 'NETWORK_ERROR';
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function safeMessage(string $classification): string
    {
        return match ($classification) {
            'AUTHENTICATION_FAILED' => 'VICIdial authentication failed for the configured reporting account.',
            'PERMISSION_DENIED' => 'VICIdial denied access to this report for the configured API account.',
            'REPORT_HTML_CHANGED' => 'VICIdial returned an unexpected report page format.',
            'PARSE_ERROR' => 'VICIdial returned report data in an unsupported format.',
            'REPORT_EMPTY' => 'VICIdial returned no report rows for the selected scope.',
            'SERVER_ERROR' => 'VICIdial returned a server error for this report.',
            'NETWORK_TIMEOUT' => 'The VICIdial report request timed out.',
            'CONNECTION_REFUSED' => 'The VICIdial report connection was refused.',
            default => 'Unable to reach the VICIdial reporting API.',
        };
    }

    private function durationMs(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logRequest(string $function, array $meta, string $message, ?string $level = null): void
    {
        $level ??= in_array($meta['classification'] ?? '', ['OK', 'REPORT_EMPTY'], true) ? 'info' : 'warning';
        $this->telephonyLogger->{$level}('VicidialNonAgentApiService', $message, array_merge($meta, [
            'function' => $function,
        ]));
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
