<?php

namespace App\Services;

use App\Models\CrmCallHistory;
use App\Support\OperationResult;
use Illuminate\Support\Collection;

class CallHistoryService
{
    public function getUnifiedHistory(string $campaignCode, ?int $leadId = null, ?string $phone = null, int $limit = 50): Collection
    {
        $q = CrmCallHistory::with('campaign')
            ->where('campaign_code', $campaignCode)
            ->orderByDesc('created_at')
            ->limit($limit);
        if ($leadId !== null) {
            $q->where('lead_id', $leadId);
        }
        if ($phone !== null && $phone !== '') {
            $q->where('phone_number', $phone);
        }
        if ($leadId === null && ($phone === null || $phone === '')) {
            return $q->get();
        }

        return $q->get();
    }

    public function getHistoryForCampaign(string $campaignCode, ?string $startDate = null, ?string $endDate = null, ?string $agent = null, int $perPage = 15)
    {
        $q = CrmCallHistory::with('campaign')->where('campaign_code', $campaignCode)->orderByDesc('created_at');
        if ($startDate) {
            $q->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $q->whereDate('created_at', '<=', $endDate);
        }
        if ($agent) {
            $q->where('agent', 'like', '%'.$agent.'%');
        }

        return $q->paginate($perPage);
    }

    public function logFormSubmission(
        string $campaignCode,
        string $formType,
        int $recordId,
        string $agent,
        ?int $leadId = null,
        ?string $phoneNumber = null,
        string $status = 'RECORDED',
        ?string $remarks = null,
    ): OperationResult {
        if ($campaignCode === '' || $formType === '' || $agent === '') {
            return OperationResult::failure('Campaign code, form type and agent are required.');
        }

        try {
            CrmCallHistory::create([
                'lead_id' => $leadId,
                'phone_number' => $phoneNumber,
                'campaign_code' => $campaignCode,
                'form_type' => $formType,
                'record_id' => $recordId,
                'agent' => $agent,
                'status' => $status,
                'remarks' => $remarks,
            ]);

            return OperationResult::success();
        } catch (\Throwable $e) {
            return OperationResult::failure($e->getMessage());
        }
    }
}
