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

    public function getFirstActiveWithNonAgentCredentials(): ?VicidialServer
    {
        return VicidialServer::query()
            ->where('is_active', true)
            ->whereNotNull('api_user')
            ->where('api_user', '!=', '')
            ->whereNotNull('api_pass')
            ->where('api_pass', '!=', '')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    public function getFirstActiveWithDatabaseCredentials(): ?VicidialServer
    {
        return VicidialServer::query()
            ->where('is_active', true)
            ->whereNotNull('db_host')
            ->where('db_host', '!=', '')
            ->whereNotNull('db_username')
            ->where('db_username', '!=', '')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }
}
