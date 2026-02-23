<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Repositories\VicidialServerRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VicidialProxyService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository
    ) {}

    /**
     * Execute a VICIdial Agent API action.
     * @return array{success: bool, raw_response: string, message: ?string}
     */
    public function execute(User $user, string $campaign, string $action, array $params = []): array
    {
        $server = $this->serverRepository->getForCampaign($campaign);
        if (!$server) {
            Log::warning('VicidialProxyService: No server for campaign', ['campaign' => $campaign]);
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
        $url = $baseUrl . $sep . http_build_query([
            'user' => $viciUser,
            'pass' => $viciPass,
            'agent_user' => $viciUser,
            'source' => $source,
            'function' => $action,
            'value' => $params['value'] ?? '',
        ]);
        if ($action === 'external_dial') {
            $url .= '&phone_code=' . urlencode($params['phone_code'] ?? '1') . '&search=YES&preview=NO&focus=YES';
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
                Log::warning('VicidialProxyService: Retrying after failure', [
                    'action' => $action,
                    'campaign' => $campaign,
                    'error' => $e->getMessage(),
                ]);
                return true;
            })
            ->get($url);

        $body = $response->body();
        $success = stripos($body, 'SUCCESS') !== false;
        $message = null;
        if (!$success) {
            if (stripos($body, 'INVALID USERNAME/PASSWORD') !== false || stripos($body, '|BAD|') !== false) {
                $message = 'VICIdial credentials were rejected.';
                Log::warning('VicidialProxyService: Credentials rejected', ['campaign' => $campaign, 'user_id' => $user->id]);
            } else {
                $message = trim($body) ?: 'Request failed';
                Log::warning('VicidialProxyService: Request failed', [
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
