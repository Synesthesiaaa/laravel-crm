<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface DispositionRepositoryInterface
{
    public function getForCampaign(string $campaignCode): Collection;
}
