<?php

namespace App\Services\Telephony;

use App\Models\User;
use Carbon\Carbon;

final readonly class HistoricalCallRecord
{
    public function __construct(
        public string $id,
        public string $uniqueCallId,
        public int $crmCampaignId,
        public string $crmCampaignCode,
        public string $vicidialCampaignId,
        public ?string $vicidialListId,
        public ?int $leadId,
        public ?string $vicidialUser,
        public ?int $crmUserId,
        public ?string $crmUserName,
        public string $agentDisplayName,
        public ?string $phoneNumber,
        public ?Carbon $callDate,
        public ?Carbon $callStartedAt,
        public ?Carbon $callEndedAt,
        public string $callDirection,
        public string $status,
        public ?string $dispositionCode,
        public string $dispositionLabel,
        public ?int $durationSeconds,
        public ?int $talkSeconds,
        public ?int $waitSeconds,
        public ?string $rawEndReason,
        public string $sourceTable,
    ) {}

    public function withCrmUser(?User $user): self
    {
        if ($user === null) {
            return $this;
        }

        return new self(
            id: $this->id,
            uniqueCallId: $this->uniqueCallId,
            crmCampaignId: $this->crmCampaignId,
            crmCampaignCode: $this->crmCampaignCode,
            vicidialCampaignId: $this->vicidialCampaignId,
            vicidialListId: $this->vicidialListId,
            leadId: $this->leadId,
            vicidialUser: $this->vicidialUser,
            crmUserId: $user->getKey(),
            crmUserName: $this->displayName($user),
            agentDisplayName: $this->displayName($user),
            phoneNumber: $this->phoneNumber,
            callDate: $this->callDate,
            callStartedAt: $this->callStartedAt,
            callEndedAt: $this->callEndedAt,
            callDirection: $this->callDirection,
            status: $this->status,
            dispositionCode: $this->dispositionCode,
            dispositionLabel: $this->dispositionLabel,
            durationSeconds: $this->durationSeconds,
            talkSeconds: $this->talkSeconds,
            waitSeconds: $this->waitSeconds,
            rawEndReason: $this->rawEndReason,
            sourceTable: $this->sourceTable,
        );
    }

    public function withDisposition(?string $code, string $label): self
    {
        return new self(
            id: $this->id,
            uniqueCallId: $this->uniqueCallId,
            crmCampaignId: $this->crmCampaignId,
            crmCampaignCode: $this->crmCampaignCode,
            vicidialCampaignId: $this->vicidialCampaignId,
            vicidialListId: $this->vicidialListId,
            leadId: $this->leadId,
            vicidialUser: $this->vicidialUser,
            crmUserId: $this->crmUserId,
            crmUserName: $this->crmUserName,
            agentDisplayName: $this->agentDisplayName,
            phoneNumber: $this->phoneNumber,
            callDate: $this->callDate,
            callStartedAt: $this->callStartedAt,
            callEndedAt: $this->callEndedAt,
            callDirection: $this->callDirection,
            status: $this->status,
            dispositionCode: $code,
            dispositionLabel: $label,
            durationSeconds: $this->durationSeconds,
            talkSeconds: $this->talkSeconds,
            waitSeconds: $this->waitSeconds,
            rawEndReason: $this->rawEndReason,
            sourceTable: $this->sourceTable,
        );
    }

    private function displayName(User $user): string
    {
        return (string) ($user->full_name ?: $user->name ?: $user->username ?: $user->vici_user ?: 'CRM user');
    }
}
