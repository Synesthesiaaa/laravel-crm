<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class ReportingService
{
    public function __construct(protected VicidialNonAgentApiService $nonAgentApi) {}

    public function callStatusStats(User $user, string $campaign, array $params, array $httpOptions = []): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'call_status_stats', array_filter([
            'campaigns' => $params['campaigns'] ?? '---ALL---',
            'query_date' => $params['query_date'] ?? now()->format('Y-m-d'),
            'ingroups' => $params['ingroups'] ?? null,
            'statuses' => $params['statuses'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''), true, $httpOptions);
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
            'datetime_start' => $this->resolveDateTimeStart($params),
            'datetime_end' => $this->resolveDateTimeEnd($params),
            'agent_user' => $params['agent_user'] ?? null,
            'campaign_id' => $this->resolveCampaignId($params),
            'group_by_campaign' => $params['group_by_campaign'] ?? 'YES',
            'stage' => $params['stage'] ?? 'pipe',
            'header' => $params['header'] ?? 'YES',
        ], static fn ($v) => $v !== null && $v !== ''), true);
    }

    public function loggedInAgents(User $user, string $campaign, array $params, array $httpOptions = []): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'logged_in_agents', array_filter([
            'campaigns' => $params['campaigns'] ?? null,
            'user_groups' => $params['user_groups'] ?? null,
            'show_sub_status' => $params['show_sub_status'] ?? 'YES',
            'stage' => $params['stage'] ?? 'pipe',
            'header' => $params['header'] ?? 'YES',
        ], static fn ($v) => $v !== null && $v !== ''), true, $httpOptions);
    }

    /**
     * Fetch the independent Supervisor reports concurrently from the VICIdial
     * server mapped to the selected CRM campaign.
     *
     * @param  array<string, int>  $httpOptions
     * @return array<string, OperationResult>
     */
    public function supervisorSnapshot(User $user, string $campaign, string $queryDate, array $httpOptions = []): array
    {
        return $this->nonAgentApi->executeBatch($user, $campaign, [
            'logged_agents' => [
                'function' => 'logged_in_agents',
                'params' => [
                    'campaigns' => '---ALL---',
                    'show_sub_status' => 'YES',
                    'stage' => 'pipe',
                    'header' => 'YES',
                ],
            ],
            'agent_performance' => [
                'function' => 'agent_stats_export',
                'params' => [
                    'datetime_start' => $queryDate.'+00:00:00',
                    'datetime_end' => $queryDate.'+23:59:59',
                    'group_by_campaign' => 'NO',
                    'time_format' => 'S',
                    'stage' => 'pipe',
                    'header' => 'YES',
                ],
            ],
            'call_totals' => [
                'function' => 'call_status_stats',
                'params' => [
                    'campaigns' => '---ALL---',
                    'query_date' => $queryDate,
                ],
            ],
        ], true, $httpOptions);
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

    public function userGroupStatus(User $user, string $campaign, string $groups, array $httpOptions = []): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'user_group_status', [
            'user_groups' => $groups,
            'stage' => 'pipe',
            'header' => 'YES',
        ], true, $httpOptions);
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

    protected function resolveCampaignId(array $params): ?string
    {
        $campaignId = trim((string) ($params['campaign_id'] ?? ''));
        if ($campaignId !== '') {
            return $campaignId;
        }

        $campaigns = trim((string) ($params['campaigns'] ?? $params['campaign'] ?? ''));
        if ($campaigns === '') {
            return null;
        }

        $normalized = strtoupper($campaigns);
        if (in_array($normalized, ['---ALL---', 'ALL', 'ALLCAMPAIGNS'], true)) {
            return null;
        }

        return $campaigns;
    }

    protected function resolveDateTimeStart(array $params): string
    {
        return $this->normalizeDateTime(
            (string) ($params['datetime_start'] ?? $params['query_date'] ?? now()->startOfDay()->format('Y-m-d')),
            '+00:00:00',
        );
    }

    protected function resolveDateTimeEnd(array $params): string
    {
        return $this->normalizeDateTime(
            (string) ($params['datetime_end'] ?? $params['end_date'] ?? $params['query_date'] ?? now()->endOfDay()->format('Y-m-d')),
            '+23:59:59',
        );
    }

    protected function normalizeDateTime(string $value, string $defaultTime): string
    {
        $value = trim($value);
        if ($value === '') {
            return now()->format('Y-m-d').$defaultTime;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value.$defaultTime;
        }

        return $value;
    }
}
