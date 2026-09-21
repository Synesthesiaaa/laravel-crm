<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\VicidialServer;

class TelephonyDiagnosticsCampaignMappingService
{
    /**
     * @return array{label: string, status: string, message: string}
     */
    public function checkCampaignServerMappings(): array
    {
        $campaigns = Campaign::query()->pluck('code');
        if ($campaigns->isEmpty()) {
            return [
                'label' => 'Campaign → ViciDial Server Mapping',
                'status' => 'warn',
                'message' => 'No campaigns found.',
            ];
        }

        $servers = VicidialServer::query()
            ->where('is_active', true)
            ->whereIn('campaign_code', $campaigns)
            ->get(['campaign_code', 'api_user', 'api_pass'])
            ->keyBy('campaign_code');

        $missing = [];
        $noApiUser = [];

        foreach ($campaigns as $code) {
            $server = $servers->get($code);
            if (! $server) {
                $missing[] = $code;
            } elseif (empty($server->api_user) || empty($server->api_pass)) {
                $noApiUser[] = $code;
            }
        }

        $issues = [];
        if ($missing !== []) {
            $issues[] = 'No active server for: '.implode(', ', $missing);
        }
        if ($noApiUser !== []) {
            $issues[] = 'Missing Non-Agent API user/pass for: '.implode(', ', $noApiUser);
        }

        if ($issues === []) {
            return [
                'label' => 'Campaign → ViciDial Server Mapping',
                'status' => 'ok',
                'message' => $campaigns->count().' campaign(s) all have active server mappings with Non-Agent API credentials.',
            ];
        }

        return [
            'label' => 'Campaign → ViciDial Server Mapping',
            'status' => 'warn',
            'message' => implode('; ', $issues),
        ];
    }
}
