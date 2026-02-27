<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use Illuminate\Support\Facades\Http;

/**
 * Syncs disposition to VICIdial when lead_id is available.
 * Uses Agent API or Non-Agent API. Fails gracefully with logging.
 */
class VicidialDispositionSyncService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger
    ) {}

    /**
     * Attempt to update lead status in VICIdial. Logs errors; does not throw.
     */
    public function syncDispositionToVicidial(CallSession $session): void
    {
        if ($session->lead_id === null) {
            return;
        }

        $server = $this->serverRepository->getForCampaign($session->campaign_code);
        if (! $server) {
            $this->telephonyLogger->debug('VicidialDispositionSyncService', 'No server for campaign', [
                'campaign' => $session->campaign_code,
            ]);

            return;
        }

        $viciCode = $this->mapLaravelToVicidial($session->disposition_code);
        $baseUrl = $this->getNonAgentApiUrl($server);
        if ($baseUrl === '') {
            return;
        }

        $params = [
            'source' => $server->source ?: config('vicidial.default_source', 'crm_tracker'),
            'user' => $server->api_user ?? '',
            'pass' => $server->api_pass ?? '',
            'function' => 'update_lead',
            'search_method' => 'lead_id',
            'lead_id' => $session->lead_id,
            'status' => $viciCode,
        ];

        if (empty($params['user']) || empty($params['pass'])) {
            $this->telephonyLogger->debug('VicidialDispositionSyncService', 'No API credentials');
            return;
        }

        try {
            $url = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($params);
            $response = Http::timeout(5)->get($url);
            $body = $response->body();
            $success = stripos($body, 'SUCCESS') !== false;

            if (! $success) {
                $this->telephonyLogger->warning('VicidialDispositionSyncService', 'Write-back failed', [
                    'lead_id' => $session->lead_id,
                    'status' => $viciCode,
                    'response' => substr($body, 0, 200),
                ]);
            }
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('VicidialDispositionSyncService', 'Exception while syncing disposition', [
                'lead_id' => $session->lead_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getNonAgentApiUrl(VicidialServer $server): string
    {
        $apiUrl = rtrim($server->api_url ?? '', '/');
        if ($apiUrl === '') {
            return '';
        }
        $nonAgent = str_contains($apiUrl, 'agc/api.php')
            ? preg_replace('#agc/api\.php.*$#', 'non_agent_api.php', $apiUrl)
            : $apiUrl;
        return $nonAgent ?: '';
    }

    protected function mapLaravelToVicidial(string $code): string
    {
        $map = config('vicidial.disposition_map', []);
        return $map[$code] ?? $code;
    }
}
