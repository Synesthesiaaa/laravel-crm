<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Repositories\VicidialServerRepository;
use Illuminate\Support\Facades\Http;

class VicidialProxyService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger
    ) {}

    /**
     * Execute a VICIdial Agent API action.
     * @return array{success: bool, raw_response: string, message: ?string}
     */
    public function execute(User $user, string $campaign, string $action, array $params = []): array
    {
        $server = $this->serverRepository->getForCampaign($campaign);
        if (!$server) {
            $this->telephonyLogger->warning('VicidialProxyService', 'No server for campaign', ['campaign' => $campaign]);
            return ['success' => false, 'raw_response' => '', 'message' => 'No VICIdial server configured for this campaign.'];
        }
        $viciUser = $user->vici_user ?? '';
        $viciPass = $user->vici_pass ?? '';
        if ($viciUser === '' || $viciPass === '') {
            return ['success' => false, 'raw_response' => '', 'message' => 'VICIdial credentials are not set for your account.'];
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
            if (!array_key_exists($k, $extraQuery)) {
                $extraQuery[$k] = $v;
            }
        }

        $url = $baseUrl . $sep . http_build_query(array_merge($baseQuery, $extraQuery));
        if ($action === 'external_dial') {
            $phoneNum = $params['phone_number'] ?? $params['value'] ?? '';
            $url .= '&phone_code=' . urlencode($params['phone_code'] ?? '1');
            if (!isset($extraQuery['search'])) {
                $url .= '&search=YES';
            }
            if (!isset($extraQuery['preview'])) {
                $url .= '&preview=NO';
            }
            if (!isset($extraQuery['focus'])) {
                $url .= '&focus=YES';
            }
            if ($phoneNum !== '') {
                $url .= '&phone_number=' . urlencode($phoneNum);
            }
        }
        if ($action === 'transfer_conference' && !empty($params['phone_number'])) {
            $url .= '&phone_number=' . urlencode($params['phone_number']);
        }

        $timeout = config('vicidial.timeout', 10);
        $connectTimeout = config('vicidial.connect_timeout', 5);
        $retryTimes = config('vicidial.retry_times', 2);
        $retrySleepMs = config('vicidial.retry_sleep_ms', 500);

        $response = Http::connectTimeout($connectTimeout)
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
        $success = !str_starts_with($normalized, 'error:');
        $message = null;
        if (!$success) {
            if (stripos($body, 'INVALID USERNAME/PASSWORD') !== false || stripos($body, '|BAD|') !== false) {
                $message = 'VICIdial credentials were rejected.';
                $this->telephonyLogger->warning('VicidialProxyService', 'Credentials rejected', ['campaign' => $campaign, 'user_id' => $user->id]);
            } else {
                $message = trim($body) ?: 'Request failed';
                $this->telephonyLogger->warning('VicidialProxyService', 'Request failed', [
                    'action' => $action,
                    'campaign' => $campaign,
                    'response' => strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body,
                ]);
            }
        }

        return [
            'success' => $success,
            'raw_response' => $body,
            'message' => $message,
        ];
    }
}
