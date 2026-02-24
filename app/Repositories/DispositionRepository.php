<?php

namespace App\Repositories;

use App\Contracts\Repositories\DispositionRepositoryInterface;
use App\Models\DispositionCode;
use Illuminate\Support\Collection;

class DispositionRepository implements DispositionRepositoryInterface
{
    public function getForCampaign(string $campaignCode): Collection
    {
        return DispositionCode::where(function ($q) use ($campaignCode) {
            $q->where('campaign_code', $campaignCode)->orWhere('campaign_code', '');
        })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
