<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialServer;
use Illuminate\Support\Collection;

final class VicidialCampaignScope
{
    /**
     * @param  Collection<int, CampaignVicidialMapping>  $mappings
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly ?VicidialServer $server,
        public readonly Collection $mappings,
    ) {}

    /**
     * Enabled mappings usable for live operational data.
     *
     * @return Collection<int, CampaignVicidialMapping>
     */
    public function liveMappings(): Collection
    {
        return $this->mappings->filter(
            fn (CampaignVicidialMapping $mapping): bool => $mapping->is_enabled
                && $mapping->status === CampaignVicidialMapping::STATUS_ACTIVE,
        )->values();
    }

    /**
     * Enabled mappings retained for historical reporting, including stale or
     * temporarily unavailable remote metadata.
     *
     * @return Collection<int, CampaignVicidialMapping>
     */
    public function historicalMappings(): Collection
    {
        return $this->mappings->filter(
            fn (CampaignVicidialMapping $mapping): bool => $mapping->is_enabled
                && $mapping->status !== CampaignVicidialMapping::STATUS_DISABLED,
        )->values();
    }

    /**
     * @return array<int, string>
     */
    public function liveCampaignCodes(): array
    {
        return $this->codes($this->liveMappings());
    }

    /**
     * @return array<int, string>
     */
    public function historicalCampaignCodes(): array
    {
        return $this->codes($this->historicalMappings());
    }

    public function contains(string $campaignCode, bool $historical = false): bool
    {
        $needle = strtolower(trim($campaignCode));
        if ($needle === '') {
            return false;
        }

        $codes = $historical ? $this->historicalCampaignCodes() : $this->liveCampaignCodes();

        return in_array($needle, array_map('strtolower', $codes), true);
    }

    /**
     * @return array<int, string>
     */
    public function narrowCampaignCodes(?string $requested, bool $historical = true): array
    {
        $allowed = $historical ? $this->historicalCampaignCodes() : $this->liveCampaignCodes();
        $requestedCodes = array_values(array_filter(array_map(
            static fn (string $code): string => trim($code),
            preg_split('/[|,\s]+/', (string) $requested, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        )));

        if ($requestedCodes === [] || in_array(strtoupper((string) $requested), ['ALL', '---ALL---', 'ALLCAMPAIGNS'], true)) {
            return $allowed;
        }

        $allowedByNormalizedCode = [];
        foreach ($allowed as $code) {
            $allowedByNormalizedCode[strtolower($code)] = $code;
        }

        $selected = [];
        foreach ($requestedCodes as $code) {
            $normalized = strtolower($code);
            if (isset($allowedByNormalizedCode[$normalized])) {
                $selected[] = $allowedByNormalizedCode[$normalized];
            }
        }

        return array_values(array_unique($selected));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crm_campaign' => [
                'id' => $this->campaign->getKey(),
                'code' => $this->campaign->code,
                'name' => $this->campaign->name,
            ],
            'server' => $this->server ? [
                'id' => $this->server->getKey(),
                'name' => $this->server->server_name,
            ] : null,
            'campaign_count' => count($this->historicalCampaignCodes()),
            'campaign_codes' => $this->historicalCampaignCodes(),
            'live_campaign_codes' => $this->liveCampaignCodes(),
            'mappings' => $this->mappings->map(fn (CampaignVicidialMapping $mapping): array => [
                'id' => $mapping->getKey(),
                'campaign_code' => $mapping->vicidial_campaign_code,
                'is_enabled' => (bool) $mapping->is_enabled,
                'status' => $mapping->status,
                'last_seen_at' => $mapping->last_seen_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, CampaignVicidialMapping>  $mappings
     * @return array<int, string>
     */
    private function codes(Collection $mappings): array
    {
        return $mappings
            ->map(fn (CampaignVicidialMapping $mapping): string => trim((string) $mapping->vicidial_campaign_code))
            ->filter()
            ->unique(fn (string $code): string => strtolower($code))
            ->values()
            ->all();
    }
}
