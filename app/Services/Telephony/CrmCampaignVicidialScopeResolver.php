<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use Illuminate\Support\Facades\Cache;

class CrmCampaignVicidialScopeResolver
{
    public function __construct(
        protected VicidialServerRepository $serverRepository,
    ) {}

    public function resolve(Campaign|string $campaign): VicidialCampaignScope
    {
        $campaignModel = $campaign instanceof Campaign
            ? $campaign
            : Campaign::query()->where('code', trim($campaign))->first();

        if ($campaignModel === null) {
            $campaignModel = new Campaign([
                'code' => trim((string) $campaign),
                'name' => trim((string) $campaign),
            ]);
            $campaignModel->id = 0;
        }

        $cacheKey = $this->cacheKey($campaignModel);
        $cacheSeconds = max(0, (int) config('vicidial.campaign_scope_cache_seconds', 60));
        if ($cacheSeconds === 0) {
            return $this->build($campaignModel);
        }

        return Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), fn (): VicidialCampaignScope => $this->build($campaignModel));
    }

    public function clear(Campaign|string $campaign): void
    {
        $campaignModel = $campaign instanceof Campaign
            ? $campaign
            : Campaign::query()->where('code', trim($campaign))->first();

        if ($campaignModel === null) {
            return;
        }

        Cache::forget($this->cacheKey($campaignModel));
    }

    public function clearForServer(VicidialServer $server): void
    {
        if ($server->campaign) {
            $this->clear($server->campaign);
        }
    }

    private function build(Campaign $campaign): VicidialCampaignScope
    {
        $mappedServerId = $campaign->exists
            ? CampaignVicidialMapping::query()
                ->where('campaign_id', $campaign->getKey())
                ->where('is_enabled', true)
                ->orderBy('id')
                ->value('vicidial_server_id')
            : null;
        $server = $campaign->exists
            ? ($mappedServerId !== null
                ? VicidialServer::query()
                    ->active()
                    ->where('campaign_code', $campaign->code)
                    ->whereKey((int) $mappedServerId)
                    ->first()
                : $this->serverRepository->getForCampaign((string) $campaign->code))
            : null;
        $mappings = $server !== null && $campaign->exists
            ? CampaignVicidialMapping::query()
                ->where('campaign_id', $campaign->getKey())
                ->forServer((int) $server->getKey())
                ->orderBy('vicidial_campaign_code')
                ->get()
            : collect();

        return new VicidialCampaignScope($campaign, $server, $mappings);
    }

    private function cacheKey(Campaign $campaign): string
    {
        return 'crm-campaign-vicidial-scope:'.($campaign->getKey() ?: sha1(strtolower((string) $campaign->code)));
    }
}
