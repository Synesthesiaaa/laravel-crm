<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class LeadService
{
    public function __construct(
        protected VicidialNonAgentApiService $nonAgentApi,
        protected VicidialProxyService $agentApi,
    ) {}

    public function search(User $user, string $campaign, string $phoneNumber): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'lead_search', [
            'phone_number' => $phoneNumber,
            'header' => 'YES',
        ], true);
    }

    public function allInfo(User $user, string $campaign, ?int $leadId = null, ?string $phoneNumber = null): OperationResult
    {
        $params = [
            'header' => 'YES',
            'custom_fields' => 'Y',
        ];
        if ($leadId) {
            $params['lead_id'] = $leadId;
        }
        if ($phoneNumber) {
            $params['phone_number'] = $phoneNumber;
        }

        return $this->nonAgentApi->execute($user, $campaign, 'lead_all_info', $params, true);
    }

    public function fieldInfo(User $user, string $campaign, int $leadId, string $fieldName): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'lead_field_info', [
            'lead_id' => $leadId,
            'field_name' => $fieldName,
        ], true);
    }

    public function add(User $user, string $campaign, array $data): OperationResult
    {
        $params = array_merge([
            'phone_number' => (string) ($data['phone_number'] ?? ''),
            'phone_code' => (string) ($data['phone_code'] ?? '1'),
            'list_id' => (string) ($data['list_id'] ?? '999'),
            'campaign_id' => (string) ($data['campaign_id'] ?? strtoupper(substr($campaign, 0, 8))),
        ], $data);

        return $this->nonAgentApi->execute($user, $campaign, 'add_lead', $params, true);
    }

    public function update(User $user, string $campaign, array $data): OperationResult
    {
        $params = array_merge([
            'search_method' => (string) ($data['search_method'] ?? 'LEAD_ID'),
        ], $data);

        return $this->nonAgentApi->execute($user, $campaign, 'update_lead', $params, true);
    }

    public function switchLead(User $user, string $campaign, int $leadId): OperationResult
    {
        $result = $this->agentApi->execute($user, $campaign, 'switch_lead', [
            'value' => '',
            'query' => ['lead_id' => $leadId],
        ]);

        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Unable to switch lead.');
        }

        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }

    public function updateFields(User $user, string $campaign, array $fields): OperationResult
    {
        $result = $this->agentApi->execute($user, $campaign, 'update_fields', [
            'value' => '',
            'query' => $fields,
        ]);

        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Unable to update lead fields.');
        }

        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }
}
