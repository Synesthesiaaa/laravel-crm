<?php

namespace App\Http\Resources;

use App\Services\Telephony\HistoricalCallRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoricalCallResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var HistoricalCallRecord $record */
        $record = $this->resource;

        return [
            'id' => $record->id,
            'unique_call_id' => $record->uniqueCallId,
            'source_table' => $record->sourceTable,
            'called_at' => $record->callDate?->toIso8601String(),
            'call_started_at' => $record->callStartedAt?->toIso8601String(),
            'call_ended_at' => $record->callEndedAt?->toIso8601String(),
            'agent' => [
                'vicidial_user' => $record->vicidialUser,
                'crm_user_id' => $record->crmUserId,
                'name' => $record->crmUserName ?: $record->agentDisplayName,
                'crm_user_available' => $record->crmUserId !== null,
            ],
            'phone_number' => $record->phoneNumber,
            'status' => $record->status,
            'raw_status' => $record->status,
            'disposition' => [
                'code' => $record->dispositionCode,
                'label' => $record->dispositionLabel,
            ],
            'duration_seconds' => $record->durationSeconds,
            'talk_seconds' => $record->talkSeconds,
            'wait_seconds' => $record->waitSeconds,
            'call_direction' => $record->callDirection,
            'crm_campaign' => [
                'id' => $record->crmCampaignId,
                'code' => $record->crmCampaignCode,
            ],
            'vicidial_campaign' => $record->vicidialCampaignId,
            'vicidial_list' => $record->vicidialListId,
            'lead_id' => $record->leadId,
            'raw_end_reason' => $record->rawEndReason,
        ];
    }
}
