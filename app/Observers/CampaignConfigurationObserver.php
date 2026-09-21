<?php

namespace App\Observers;

use App\Services\CampaignService;
use Illuminate\Database\Eloquent\Model;

class CampaignConfigurationObserver
{
    public function saved(Model $model): void
    {
        $this->clearCampaignCache();
    }

    public function deleted(Model $model): void
    {
        $this->clearCampaignCache();
    }

    public function restored(Model $model): void
    {
        $this->clearCampaignCache();
    }

    public function forceDeleted(Model $model): void
    {
        $this->clearCampaignCache();
    }

    private function clearCampaignCache(): void
    {
        app(CampaignService::class)->clearCampaignsCache();
    }
}
