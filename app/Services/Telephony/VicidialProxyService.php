<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Repositories\VicidialServerRepository;
use App\Support\CallErrors;
use Illuminate\Support\Facades\Http;

class VicidialProxyService
{
    private const RESERVED_TRANSPORT_PARAMETERS = [
        'function', 'source', 'user', 'pass', 'agent_user',
    ];

    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Execute a VICIdial Agent API action.
     *
     * @return array{success: bool, raw_response: string, message: ?string, failure_code: ?string, outcome_unknown?: bool}
     */
    public function execute(User $user, string $campaign, string $action, array $params = []): array
    {
        if ($this->hasReservedTransportParameters($params)) {
            return [
                'success' => false,
                'raw_response' => '',
                'message' => CallErrors::MESSAGES[CallErrors::INVALID_VICIDIAL_PARAMETERS],
                'failure_code' => CallErrors::INVALID_VICIDIAL_PARAMETERS,
            ];
        }

        $server = $this->serverRepository->getForCampaign($campaign);
        if (! $server) {
            $this->telephonyLogger->warning('VicidialProxyService', 'No server for campaign', ['campaign' => $campaign]);

            return [
                'success' => false,
                'raw_response' => '',
                'message' => 'No VICIdial server configured for this campaign.',
                'failure_code' => CallErrors::VICIDIAL_UNAVAILABLE,
            ];
        }
        $overrideCreds = is_array($params['credentials'] ?? null) ? $params['credentials'] : [];
        $viciUser = (string) ($overrideCreds['vici_user'] ?? ($user->vici_user ?? ''));
        $viciPass = (string) ($overrideCreds['vici_pass'] ?? ($user->vici_pass ?? ''));
        if ($viciUser === '' || $viciPass === '') {
            return [
                'success' => false,
                'raw_response' => '',
                'message' => 'VICIdial credentials are not set for your account.',
                'failure_code' => CallErrors::EXTENSION_OFFLINE,
            ];
        }

        $source = $server->source ?: config('vicidial.default_source', 'crm_tracker');
        $baseUrl = rtrim($server->api_url, '?&');
        $sep = str_contains($baseUrl, '?') ? '&' : '?';

        $baseQuery = [
            'user' => $viciUser,
            'pass' => $viciPass,
            'agent_user' => $viciUser,
            'source' => $source,
            'function' => $action,
            'value' => $params['value'] ?? '',
        ];

        $extraQuery = $params['query'] ?? [];
        $targetAgentUser = $params['target_agent_user'] ?? null;
        if ($targetAgentUser !== null) {
            if (! is_string($targetAgentUser) || trim($targetAgentUser) === '' || strlen($targetAgentUser) > 20) {
                return [
                    'success' => false,
                    'raw_response' => '',
                    'message' => CallErrors::MESSAGES[CallErrors::INVALID_VICIDIAL_PARAMETERS],
                    'failure_code' => CallErrors::INVALID_VICIDIAL_PARAMETERS,
                ];
            }

            // Only trusted server-side callers may set the controlled supervisor target.
            // Request-supplied query.agent_user remains rejected by the reserved-key guard.
            $extraQuery['agent_user'] = trim($targetAgentUser);
        }
        foreach ($params as $k => $v) {
            if (in_array($k, ['value', 'phone_code', 'phone_number', 'query', 'credentials', 'target_agent_user'], true)) {
                continue;
            }
            if (! array_key_exists($k, $extraQuery)) {
                $extraQuery[$k] = $v;
            }
        }

        $url = $baseUrl.$sep.http_build_query(array_merge($baseQuery, $extraQuery));
        if ($action === 'external_dial') {
            $phoneNum = $params['phone_number'] ?? $params['value'] ?? '';
            $url .= '&phone_code='.urlencode($params['phone_code'] ?? '1');
            if (! isset($extraQuery['search'])) {
                $url .= '&search=YES';
            }
            if (! isset($extraQuery['preview'])) {
                $url .= '&preview=NO';
            }
            if (! isset($extraQuery['focus'])) {
                $url .= '&focus=YES';
            }
            if ($phoneNum !== '') {
                $url .= '&phone_number='.urlencode($phoneNum);
            }
        }
        if ($action === 'transfer_conference' && ! empty($params['phone_number'])) {
            $url .= '&phone_number='.urlencode($params['phone_number']);
        }

        $timeout = max(1, (int) config('vicidial.agent_api_timeout', 4));
        $connectTimeout = max(1, (int) config('vicidial.agent_connect_timeout', 2));
        $retryTimes = max(1, (int) config('vicidial.retry_times', 2));
        $retrySleepMs = config('vicidial.retry_sleep_ms', 500);
        $retrySafe = $this->isRetrySafeAction($action, $params);
        $attempts = $retrySafe ? $retryTimes : 1;

        try {
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->retry($attempts, $retrySleepMs, function (\Throwable $e, $request) use ($action, $campaign) {
                    $this->telephonyLogger->warning('VicidialProxyService', 'Retrying after failure', [
                        'action' => $action,
                        'campaign' => $campaign,
                        'exception_class' => $e::class,
                    ]);

                    return true;
                })
                ->get($url);
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('VicidialProxyService', 'Agent API transport failure', [
                'action' => $action,
                'campaign' => $campaign,
                'exception_class' => $e::class,
                'outcome_unknown' => ! $retrySafe,
            ]);

            return [
                'success' => false,
                'raw_response' => '',
                'message' => ! $retrySafe
                    ? CallErrors::MESSAGES[CallErrors::VICIDIAL_OUTCOME_UNKNOWN]
                    : 'VICIdial is temporarily unavailable. Please try again.',
                'failure_code' => ! $retrySafe
                    ? CallErrors::VICIDIAL_OUTCOME_UNKNOWN
                    : CallErrors::VICIDIAL_UNAVAILABLE,
                'outcome_unknown' => ! $retrySafe,
            ];
        }

        $body = $response->body();
        $normalized = strtolower(trim($body));
        $definitiveApiError = str_starts_with($normalized, 'error:');
        $outcomeUnknown = ! $retrySafe
            && ! $definitiveApiError
            && (! $response->successful() || $normalized === '');
        $success = ! $definitiveApiError && ! $outcomeUnknown && $response->successful();
        $message = null;
        $failureCode = null;
        if ($outcomeUnknown) {
            $message = CallErrors::MESSAGES[CallErrors::VICIDIAL_OUTCOME_UNKNOWN];
            $failureCode = CallErrors::VICIDIAL_OUTCOME_UNKNOWN;
        }
        if (! $success) {
            if ($outcomeUnknown) {
                // Keep the uncertain result distinct from a definitive rejection.
            } elseif (stripos($body, 'INVALID USERNAME/PASSWORD') !== false || stripos($body, '|BAD|') !== false) {
                $message = 'VICIdial credentials were rejected.';
                $failureCode = CallErrors::AUTH_FAILURE;
                $this->telephonyLogger->warning('VicidialProxyService', 'Credentials rejected', ['campaign' => $campaign, 'user_id' => $user->id]);
            } elseif (self::isAgentNotLoggedInBody($body)) {
                $message = 'Agent is not logged into an active VICIdial session. Please use VICIdial Session Login first.';
                $failureCode = CallErrors::VICIDIAL_AGENT_NOT_LOGGED_IN;
                $this->telephonyLogger->warning('VicidialProxyService', 'Agent not logged in for action', [
                    'campaign' => $campaign,
                    'user_id' => $user->id,
                    'action' => $action,
                ]);
            } else {
                $message = trim($body) ?: 'Request failed';
                $failureCode = CallErrors::VICIDIAL_DIAL_FAILED;
                $this->telephonyLogger->warning('VicidialProxyService', 'Request failed', [
                    'action' => $action,
                    'campaign' => $campaign,
                    'response_bytes' => strlen($body),
                ]);
            }
        }

        return [
            'success' => $success,
            'raw_response' => $body,
            'message' => $message,
            'failure_code' => $success ? null : $failureCode,
            'outcome_unknown' => $outcomeUnknown,
        ];
    }

    /**
     * Only retry actions that are read-only. Replaying Agent API writes after a
     * response timeout can duplicate dials or repeat state transitions.
     */
    private function isRetrySafeAction(string $action, array $params): bool
    {
        return $action === 'calls_in_queue_count'
            || ($action === 'recording' && strtoupper((string) ($params['value'] ?? '')) === 'STATUS');
    }

    private function hasReservedTransportParameters(array $params): bool
    {
        foreach ($params as $key => $value) {
            if (in_array(strtolower((string) $key), self::RESERVED_TRANSPORT_PARAMETERS, true)) {
                return true;
            }

            if ($key === 'query' && is_array($value)) {
                foreach ($value as $queryKey => $_queryValue) {
                    if (in_array(strtolower((string) $queryKey), self::RESERVED_TRANSPORT_PARAMETERS, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Detect VICIdial Agent API responses that mean the agent has no live session.
     */
    private static function isAgentNotLoggedInBody(string $body): bool
    {
        $u = strtoupper($body);

        return str_contains($u, 'AGENT NOT LOGGED IN')
            || str_contains($u, 'AGENT_USER IS NOT LOGGED IN')
            || str_contains($u, 'NO LIVE AGENT')
            || str_contains($u, 'MUST LOGIN')
            || str_contains($u, 'MUST LOG IN');
    }
}
