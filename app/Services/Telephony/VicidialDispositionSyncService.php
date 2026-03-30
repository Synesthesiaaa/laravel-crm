<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use Illuminate\Support\Facades\Http;

/**
 * Syncs disposition to VICIdial when lead_id is available.
 * Uses Non-Agent API for lead update AND Agent API external_status
 * so the ViciDial agent session is properly advanced past the dispo screen.
 */
class VicidialDispositionSyncService
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
        protected VicidialProxyService $vicidialProxy,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Attempt to update lead status in VICIdial and advance agent past DISPO.
     * Logs errors; does not throw.
     *
     * @return array{status: string, message?: string} Safe for JSON to browsers (no secrets).
     */
    public function syncDispositionToVicidial(CallSession $session): array
    {
        if ($session->lead_id === null) {
            return $this->outcome('skipped', 'No lead on this call; dialer sync was not sent.');
        }

        $server = $this->serverRepository->getForCampaign($session->campaign_code);
        if (! $server) {
            $this->telephonyLogger->debug('VicidialDispositionSyncService', 'No server for campaign', [
                'campaign' => $session->campaign_code,
            ]);

            return $this->outcome('skipped', 'Dialer is not configured for this campaign.');
        }

        $viciCode = $this->mapLaravelToVicidial((string) $session->disposition_code);
        $baseUrl = $this->getNonAgentApiUrl($server);
        if ($baseUrl === '') {
            return $this->outcome('skipped', 'Dialer API URL is not available.');
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

            return $this->outcome('skipped', 'Dialer API credentials are not configured.');
        }

        $leadOk = false;

        try {
            $url = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').http_build_query($params);
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->timeout(5)->get($url);
            $body = $response->body();
            $leadOk = stripos($body, 'SUCCESS') !== false;

            if (! $leadOk) {
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
            $leadOk = false;
        }

        $extOk = $this->sendExternalStatus($session, $viciCode);

        if ($leadOk && $extOk) {
            return $this->outcome('synced');
        }

        if (! $leadOk && ! $extOk) {
            return $this->outcome('failed', 'Disposition saved in CRM; the dialer did not confirm this disposition.');
        }

        return $this->outcome('partial', 'Disposition saved in CRM; dialer sync completed only partially.');
    }

    /**
     * Call Agent API external_status so ViciDial moves the agent past disposition.
     */
    protected function sendExternalStatus(CallSession $session, string $viciCode): bool
    {
        $user = $session->user;
        if (! $user) {
            return false;
        }

        try {
            $result = $this->vicidialProxy->execute($user, $session->campaign_code, 'external_status', [
                'value' => $viciCode,
            ]);

            if (! $result['success']) {
                $this->telephonyLogger->warning('VicidialDispositionSyncService', 'external_status failed', [
                    'session_id' => $session->id,
                    'status' => $viciCode,
                    'response' => $result['raw_response'],
                ]);
            }

            return (bool) $result['success'];
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('VicidialDispositionSyncService', 'external_status exception (non-blocking)', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    protected function outcome(string $status, ?string $message = null): array
    {
        $payload = ['status' => $status];
        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return $payload;
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
