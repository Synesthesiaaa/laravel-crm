<?php

namespace App\Repositories;

use App\Contracts\Repositories\VicidialServerRepositoryInterface;
use App\Models\VicidialServer;
use Illuminate\Database\Eloquent\Collection;

class VicidialServerRepository implements VicidialServerRepositoryInterface
{
    public function getForCampaign(string $campaignCode): ?VicidialServer
    {
        $default = VicidialServer::where('campaign_code', $campaignCode)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('priority')
            ->first();
        if ($default) {
            return $default;
        }
        return VicidialServer::where('campaign_code', $campaignCode)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    public function getAllForCampaign(string $campaignCode): Collection
    {
        return VicidialServer::where('campaign_code', $campaignCode)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }
}
