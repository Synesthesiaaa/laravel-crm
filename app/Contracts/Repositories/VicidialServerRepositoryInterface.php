<?php

namespace App\Contracts\Repositories;

use App\Models\VicidialServer;
use Illuminate\Database\Eloquent\Collection;

interface VicidialServerRepositoryInterface
{
    public function getForCampaign(string $campaignCode): ?VicidialServer;

    public function getAllForCampaign(string $campaignCode): Collection;

    /**
     * First active server with Non-Agent API credentials (for agent_campaigns, etc.).
     */
    public function getFirstActiveWithNonAgentCredentials(): ?VicidialServer;

    /**
     * First active server with MySQL credentials (read-only lookups).
     */
    public function getFirstActiveWithDatabaseCredentials(): ?VicidialServer;
}
