<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class ReportingService
{
    public function __construct(protected VicidialNonAgentApiService $nonAgentApi) {}

    public function callStatusStats(User $user, string $campaign, array $params): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'call_status_stats', array_filter([
            'campaigns' => $params['campaigns'] ?? '---ALL---',
            'query_date' => $params['query_date'] ?? now()->format('Y-m-d'),
            'ingroups' => $params['ingroups'] ?? null,
            'statuses' => $params['statuses'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''), true);
    }

    public function callDispoReport(User $user, string $campaign, array $params): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'call_dispo_report', array_filter([
            'campaigns' => $params['campaigns'] ?? null,
            'ingroups' => $params['ingroups'] ?? null,
            'dids' => $params['dids'] ?? null,
            'query_date' => $params['query_date'] ?? now()->format('Y-m-d'),
            'end_date' => $params['end_date'] ?? now()->format('Y-m-d'),
            'statuses' => $params['statuses'] ?? null,
            'status_breakdown' => $params['status_breakdown'] ?? 1,
            'show_percentages' => $params['show_percentages'] ?? 1,
        ], static fn ($v) => $v !== null && $v !== ''), true);
    }

    public function agentStats(User $user, string $campaign, array $params): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'agent_stats_export', array_filter([
            'datetime_start' => $params['datetime_start'] ?? now()->startOfDay()->format('Y-m-d+H:i:s'),
            'datetime_end' => $params['datetime_end'] ?? now()->endOfDay()->format('Y-m-d+H:i:s'),
            'agent_user' => $params['agent_user'] ?? null,
            'campaign_id' => $params['campaign_id'] ?? null,
            'group_by_campaign' => $params['group_by_campaign'] ?? 'YES',
            'stage' => $params['stage'] ?? 'pipe',
            'header' => $params['header'] ?? 'YES',
        ], static fn ($v) => $v !== null && $v !== ''), true);
    }

    public function loggedInAgents(User $user, string $campaign, array $params): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'logged_in_agents', array_filter([
            'campaigns' => $params['campaigns'] ?? null,
            'user_groups' => $params['user_groups'] ?? null,
            'show_sub_status' => $params['show_sub_status'] ?? 'YES',
            'stage' => $params['stage'] ?? 'pipe',
            'header' => $params['header'] ?? 'YES',
        ], static fn ($v) => $v !== null && $v !== ''), true);
    }

    public function phoneNumberLog(User $user, string $campaign, string $numbers): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'phone_number_log', [
            'phone_number' => $numbers,
            'stage' => 'pipe',
            'header' => 'YES',
            'type' => 'ALL',
        ], true);
    }

    public function userGroupStatus(User $user, string $campaign, string $groups): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'user_group_status', [
            'user_groups' => $groups,
            'stage' => 'pipe',
            'header' => 'YES',
        ], true);
    }

    public function inGroupStatus(User $user, string $campaign, string $groups): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'in_group_status', [
            'in_groups' => $groups,
            'stage' => 'pipe',
            'header' => 'YES',
        ], true);
    }

    public function agentStatus(User $user, string $campaign, string $agentUser): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'agent_status', [
            'agent_user' => $agentUser,
            'stage' => 'pipe',
            'header' => 'YES',
            'include_ip' => 'YES',
        ], true);
    }
}
