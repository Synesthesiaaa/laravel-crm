<?php

namespace App\Contracts\Repositories;

use App\Models\VicidialServer;
use Illuminate\Database\Eloquent\Collection;

interface VicidialServerRepositoryInterface
{
    public function getForCampaign(string $campaignCode): ?VicidialServer;

    public function getAllForCampaign(string $campaignCode): Collection;
}
