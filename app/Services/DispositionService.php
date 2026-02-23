<?php

namespace App\Services;

use App\Models\CampaignDispositionRecord;
use App\Repositories\DispositionRepository;
use App\Support\OperationResult;

class DispositionService
{
    public function __construct(
        protected DispositionRepository $dispositionRepository
    ) {}

    public function getCodesForCampaign(string $campaignCode): array
    {
        return $this->dispositionRepository->getForCampaign($campaignCode)
            ->map(fn ($c) => ['code' => $c->code, 'label' => $c->label, 'sort_order' => $c->sort_order])
            ->all();
    }

    public function saveDisposition(
        string $campaignCode,
        string $agent,
        string $dispositionCode,
        string $dispositionLabel,
        ?int $leadId = null,
        ?string $phoneNumber = null,
        ?string $remarks = null,
        ?int $callDurationSeconds = null,
        ?string $leadDataJson = null
    ): OperationResult {
        try {
            CampaignDispositionRecord::create([
                'campaign_code' => $campaignCode,
                'agent' => $agent,
                'disposition_code' => $dispositionCode,
                'disposition_label' => $dispositionLabel,
                'lead_id' => $leadId,
                'phone_number' => $phoneNumber,
                'remarks' => $remarks,
                'call_duration_seconds' => $callDurationSeconds,
                'lead_data_json' => $leadDataJson,
                'called_at' => now(),
            ]);
            return OperationResult::success();
        } catch (\Throwable $e) {
            return OperationResult::failure($e->getMessage());
        }
    }
}
