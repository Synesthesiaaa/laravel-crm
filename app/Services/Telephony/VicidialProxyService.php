<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Repositories\VicidialServerRepository;
use App\Support\CallErrors;
use Illuminate\Support\Facades\Http;

class VicidialProxyService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Execute a VICIdial Agent API action.
     *
     * @return array{success: bool, raw_response: string, message: ?string, failure_code: ?string}
     */
    public function execute(User $user, string $campaign, string $action, array $params = []): array
    {
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
        $viciUser = $user->vici_user ?? '';
        $viciPass = $user->vici_pass ?? '';
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
        foreach ($params as $k => $v) {
            if (in_array($k, ['value', 'phone_code', 'phone_number', 'query'], true)) {
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

        $timeout = config('vicidial.timeout', 10);
        $connectTimeout = config('vicidial.connect_timeout', 5);
        $retryTimes = config('vicidial.retry_times', 2);
        $retrySleepMs = config('vicidial.retry_sleep_ms', 500);

        $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry($retryTimes, $retrySleepMs, function (\Throwable $e, $request) use ($action, $campaign) {
                $this->telephonyLogger->warning('VicidialProxyService', 'Retrying after failure', [
                    'action' => $action,
                    'campaign' => $campaign,
                    'error' => $e->getMessage(),
                ]);

                return true;
            })
            ->get($url);

        $body = $response->body();
        $normalized = strtolower(trim($body));
        $success = ! str_starts_with($normalized, 'error:');
        $message = null;
        $failureCode = null;
        if (! $success) {
            if (stripos($body, 'INVALID USERNAME/PASSWORD') !== false || stripos($body, '|BAD|') !== false) {
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
                    'response' => strlen($body) > 200 ? substr($body, 0, 200).'...' : $body,
                ]);
            }
        }

        return [
            'success' => $success,
            'raw_response' => $body,
            'message' => $message,
            'failure_code' => $success ? null : $failureCode,
        ];
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
