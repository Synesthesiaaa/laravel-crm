<?php

namespace App\Services;

use App\Repositories\CampaignRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CampaignService
{
    public function __construct(
        protected CampaignRepository $campaignRepository,
    ) {}

    public function getCampaigns(): array
    {
        return Cache::remember('campaigns_with_forms', 300, function () {
            $fromDb = $this->campaignRepository->getCampaignsWithForms();
            if (! empty($fromDb)) {
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

    /**
     * Resolve an active campaign for the current request and keep session state in sync.
     *
     * @return array{code: string, config: array<string, mixed>}
     */
    public function resolveCampaignForRequest(Request $request, ?string $requestedCode = null): array
    {
        $campaigns = $this->getCampaigns();
        $code = trim((string) $requestedCode);

        if ($code === '' || ! isset($campaigns[$code])) {
            $code = (string) $request->session()->get('campaign', '');
        }

        if ($code === '' || ! isset($campaigns[$code])) {
            $code = (string) array_key_first($campaigns);
        }

        abort_if($code === '' || ! isset($campaigns[$code]), 404, 'No active campaign configured.');

        $config = $campaigns[$code];
        $request->session()->put('campaign', $code);
        $request->session()->put('campaign_name', $config['name'] ?? $code);

        return [
            'code' => $code,
            'config' => $config,
        ];
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
