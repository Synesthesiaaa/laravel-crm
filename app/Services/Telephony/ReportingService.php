<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Models\VicidialServer;
use App\Support\OperationResult;
use Illuminate\Support\Facades\Cache;

class ReportingService
{
    public function __construct(
        protected VicidialNonAgentApiService $nonAgentApi,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
    ) {}

    public function callStatusStats(User $user, string $campaign, array $params, array $httpOptions = []): OperationResult
    {
        $campaigns = $this->scopedCampaigns($campaign, $params['campaigns'] ?? null);
        if ($campaigns instanceof OperationResult) {
            return $campaigns;
        }

        return $this->executeScoped($user, $campaign, 'call_status_stats', array_filter([
            'campaigns' => $campaigns,
            'query_date' => $params['query_date'] ?? now()->format('Y-m-d'),
            'end_date' => $params['end_date'] ?? null,
            'ingroups' => $params['ingroups'] ?? null,
            'statuses' => $params['statuses'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''), true, $httpOptions);
    }

    public function callDispoReport(User $user, string $campaign, array $params): OperationResult
    {
        $campaigns = $this->scopedCampaigns($campaign, $params['campaigns'] ?? null);
        if ($campaigns instanceof OperationResult) {
            return $campaigns;
        }

        return $this->executeScoped($user, $campaign, 'call_dispo_report', array_filter([
            'campaigns' => $campaigns,
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
        $campaigns = $this->scopedCampaigns($campaign, $params['campaigns'] ?? $params['campaign_id'] ?? null);
        if ($campaigns instanceof OperationResult) {
            return $campaigns;
        }

        return $this->executeScoped($user, $campaign, 'agent_stats_export', array_filter([
            'datetime_start' => $this->resolveDateTimeStart($params),
            'datetime_end' => $this->resolveDateTimeEnd($params),
            'agent_user' => $params['agent_user'] ?? null,
            'campaign_id' => $this->singleCampaignCode($campaigns),
            'group_by_campaign' => $params['group_by_campaign'] ?? 'YES',
            'stage' => $params['stage'] ?? 'pipe',
            'header' => $params['header'] ?? 'YES',
        ], static fn ($v) => $v !== null && $v !== ''), true);
    }

    public function loggedInAgents(User $user, string $campaign, array $params, array $httpOptions = []): OperationResult
    {
        $campaigns = $this->scopedCampaigns($campaign, $params['campaigns'] ?? null, false);
        if ($campaigns instanceof OperationResult) {
            return $campaigns;
        }

        return $this->executeScoped($user, $campaign, 'logged_in_agents', array_filter([
            'campaigns' => $campaigns,
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
    public function supervisorSnapshot(User $user, string $campaign, string $queryDate, array $httpOptions = [], ?VicidialServer $server = null): array
    {
        $scope = $this->scopeResolver->resolve($campaign);
        $server = $scope->server;
        $campaigns = implode('|', $scope->liveCampaignCodes());
        if ($server === null || $campaigns === '') {
            $failure = OperationResult::failure(
                "No permitted VICIdial campaigns are mapped to CRM campaign '{$campaign}'.",
                null,
                ['classification' => $server === null ? 'NOT_CONFIGURED' : 'NO_CAMPAIGNS_MAPPED'],
            );

            return [
                'logged_agents' => $failure,
                'agent_performance' => $failure,
                'call_totals' => $failure,
            ];
        }

        $requests = [
            'logged_agents' => [
                'function' => 'logged_in_agents',
                'params' => [
                    'campaigns' => $campaigns,
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
                    'campaigns' => $campaigns,
                    'query_date' => $queryDate,
                ],
            ],
        ];
        $cacheSeconds = max(0, (int) config('vicidial.supervisor.remote_cache_seconds', 0));
        if ($cacheSeconds === 0) {
            return $server === null
                ? $this->nonAgentApi->executeBatch($user, $campaign, $requests, true, $httpOptions)
                : $this->nonAgentApi->executeBatch($user, $campaign, $requests, true, $httpOptions, $server);
        }

        $server ??= $this->nonAgentApi->getServerForCampaign($campaign);
        if ($server === null) {
            return $this->nonAgentApi->executeBatch($user, $campaign, $requests, true, $httpOptions);
        }

        $cacheKey = sprintf(
            'vicidial:supervisor:%s:%s:%s:%s',
            $server->getKey(),
            sha1($campaign.'|'.$campaigns),
            $queryDate,
            $scope->campaign->getKey(),
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $snapshot = $this->nonAgentApi->executeBatch($user, $campaign, $requests, true, $httpOptions, $server);
        if (collect($snapshot)->contains(fn (OperationResult $result): bool => $result->success)) {
            Cache::put($cacheKey, $snapshot, now()->addSeconds($cacheSeconds));
        }

        return $snapshot;
    }

    /**
     * Fetch the independent historical report sources in one server-scoped
     * batch so the Reports dashboard does not create a request waterfall.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, int>  $httpOptions
     * @return array<string, OperationResult>
     */
    public function historicalSnapshot(User $user, string $campaign, array $params, array $httpOptions = [], ?VicidialServer $server = null): array
    {
        $server ??= $this->scopeResolver?->resolve($campaign)->server;
        $campaigns = $this->scopedCampaigns($campaign, $params['campaigns'] ?? null);
        if ($campaigns instanceof OperationResult) {
            return [
                'call_status' => $campaigns,
                'agent_stats' => $campaigns,
                'call_dispo' => $campaigns,
            ];
        }

        return $this->nonAgentApi->executeBatch($user, $campaign, [
            'call_status' => [
                'function' => 'call_status_stats',
                'params' => array_filter([
                    'campaigns' => $campaigns,
                    'query_date' => $params['query_date'] ?? now()->format('Y-m-d'),
                    'end_date' => $params['end_date'] ?? null,
                    'ingroups' => $params['ingroups'] ?? null,
                    'statuses' => $params['statuses'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
            'agent_stats' => [
                'function' => 'agent_stats_export',
                'params' => array_filter([
                    'datetime_start' => $this->resolveDateTimeStart($params),
                    'datetime_end' => $this->resolveDateTimeEnd($params),
                    'agent_user' => $params['agent_user'] ?? null,
                    'campaign_id' => $this->singleCampaignCode($campaigns),
                    'group_by_campaign' => $params['group_by_campaign'] ?? 'YES',
                    'stage' => $params['stage'] ?? 'pipe',
                    'header' => $params['header'] ?? 'YES',
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
            'call_dispo' => [
                'function' => 'call_dispo_report',
                'params' => array_filter([
                    'campaigns' => $campaigns,
                    'ingroups' => $params['ingroups'] ?? null,
                    'dids' => $params['dids'] ?? null,
                    'query_date' => $params['query_date'] ?? now()->format('Y-m-d'),
                    'end_date' => $params['end_date'] ?? now()->format('Y-m-d'),
                    'statuses' => $params['statuses'] ?? null,
                    'status_breakdown' => $params['status_breakdown'] ?? 1,
                    'show_percentages' => $params['show_percentages'] ?? 1,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        ], true, $httpOptions, $server);
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
        $campaigns = $this->scopedCampaigns($campaign, null, false);
        if ($campaigns instanceof OperationResult) {
            return $campaigns;
        }

        return $this->executeScoped($user, $campaign, 'user_group_status', [
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

    private function scopedCampaigns(string $campaign, mixed $requested = null, bool $historical = true): string|OperationResult
    {
        $scope = $this->scopeResolver->resolve($campaign);
        if ($scope->server === null) {
            return OperationResult::failure(
                "No VICIdial server is configured for campaign '{$campaign}'.",
                null,
                ['classification' => 'NOT_CONFIGURED'],
            );
        }
        $allowed = $scope->narrowCampaignCodes($requested === null ? null : (string) $requested, $historical);
        if ($allowed === []) {
            return OperationResult::failure(
                "No permitted VICIdial campaigns are mapped to CRM campaign '{$campaign}'.",
                null,
                ['classification' => 'NO_CAMPAIGNS_MAPPED'],
            );
        }

        return implode('|', $allowed);
    }

    private function executeScoped(
        User $user,
        string $campaign,
        string $function,
        array $params,
        bool $useServerCredentials = true,
        array $httpOptions = [],
    ): OperationResult {
        $scope = $this->scopeResolver->resolve($campaign);
        if ($scope->server === null) {
            return OperationResult::failure(
                "No VICIdial server is configured for campaign '{$campaign}'.",
                null,
                ['classification' => 'NOT_CONFIGURED'],
            );
        }

        return $this->nonAgentApi->executeOnServer(
            $user,
            $scope->server,
            $campaign,
            $function,
            $params,
            $useServerCredentials,
            $httpOptions,
        );
    }

    private function singleCampaignCode(string $campaigns): ?string
    {
        $codes = array_values(array_filter(
            explode('|', $campaigns),
            static fn (string $code): bool => trim($code) !== '',
        ));

        return count($codes) === 1 ? $codes[0] : null;
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
