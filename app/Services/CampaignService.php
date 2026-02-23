<?php

namespace App\Services;

use App\Repositories\CampaignRepository;
use Illuminate\Support\Facades\Cache;

class CampaignService
{
    public function __construct(
        protected CampaignRepository $campaignRepository
    ) {}

    public function getCampaigns(): array
    {
        return Cache::remember('campaigns_with_forms', 300, function () {
            $fromDb = $this->campaignRepository->getCampaignsWithForms();
            if (!empty($fromDb)) {
                return $fromDb;
            }
            return config('campaigns.fallback', []);
        });
    }

    public function getCampaign(string $code): ?array
    {
        $campaigns = $this->getCampaigns();
        return $campaigns[$code] ?? null;
    }

    public function getFormConfig(string $campaignCode, string $formCode): ?array
    {
        return $this->campaignRepository->getFormConfig($campaignCode, $formCode);
    }

    public function getAllFormTableNames(): array
    {
        return $this->campaignRepository->getAllFormTableNames();
    }

    public function clearCampaignsCache(): void
    {
        Cache::forget('campaigns_with_forms');
    }
}
