<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\CrmCallHistory;
use App\Models\DispositionCode;
use App\Models\User;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\HistoricalCallHistoryPage;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use App\Support\OperationResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class CallHistoryService
{
    public function __construct(
        protected VicidialHistoricalCallProvider $historicalProvider,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
    ) {}

    /**
     * Resolve the authoritative VICIdial Call History page for a CRM campaign.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getHistoricalHistory(
        User $viewer,
        string $campaignCode,
        array $filters = [],
        bool $personal = false,
        int $perPage = 25,
    ): HistoricalCallHistoryPage {
        $scope = $this->scopeResolver->resolve($campaignCode);
        $scopeData = $scope->toArray(true);
        $campaignCodes = $scope->historicalCampaignCodes();
        $filterOptions = [
            'agents' => [],
            'statuses' => [],
            'dispositions' => [],
            'campaigns' => $campaignCodes,
            'directions' => ['OUTBOUND', 'INBOUND'],
        ];

        if ($scope->server === null || $campaignCodes === []) {
            return $this->unavailableHistoricalPage(
                $scopeData,
                $filterOptions,
                $scope->server === null
                    ? "No VICIdial server is configured for campaign '{$campaignCode}'."
                    : "No permitted VICIdial campaigns are mapped to CRM campaign '{$campaignCode}'.",
                $scope->server === null ? 'NOT_CONFIGURED' : 'NO_CAMPAIGNS_MAPPED',
                perPage: $perPage,
            );
        }

        $requestedCampaigns = trim((string) ($filters['vicidial_campaign'] ?? ''));
        $selectedCampaignCodes = $scope->narrowCampaignCodes($requestedCampaigns, true);
        if ($requestedCampaigns !== '' && $selectedCampaignCodes === []) {
            return $this->unavailableHistoricalPage(
                $scopeData,
                $filterOptions,
                'The selected VICIdial campaign is not mapped to this CRM campaign.',
                'UNAUTHORIZED_SCOPE',
                perPage: $perPage,
            );
        }

        $dispositionData = $this->dispositionData($campaignCode);
        $filterOptions['dispositions'] = $dispositionData['options'];
        $dispositionStatuses = $this->resolveDispositionFilter($filters['disposition'] ?? null, $dispositionData);
        $filters['statuses'] = $dispositionStatuses !== []
            ? $dispositionStatuses
            : (($filters['status'] ?? '') !== '' ? [(string) $filters['status']] : []);
        $personalAgent = null;
        if ($personal) {
            $agent = trim((string) ($viewer->vici_user ?? ''));
            if ($agent === '') {
                return $this->unavailableHistoricalPage(
                    $scopeData,
                    $filterOptions,
                    'Your VICIdial user mapping is not configured, so personal Call History is unavailable.',
                    'AGENT_MAPPING_NOT_CONFIGURED',
                    perPage: $perPage,
                );
            }
            $personalAgent = $agent;
            $filters['agent'] = $agent;
        }

        $page = max(1, (int) ($filters['page'] ?? Paginator::resolveCurrentPage('page')));
        $providerResult = $this->historicalProvider->fetch(
            $scope->server,
            $scope->campaign,
            $selectedCampaignCodes,
            $filters,
            $page,
            $perPage,
        );
        if (! $providerResult->success) {
            return $this->unavailableHistoricalPage(
                $scopeData,
                $filterOptions,
                $providerResult->message ?? 'VICIdial call history is currently unavailable. Please try again.',
                (string) ($providerResult->meta['classification'] ?? 'REMOTE_DATABASE_ERROR'),
                $providerResult->meta,
                perPage: $perPage,
            );
        }

        $agentLogins = $providerResult->filterOptions['agents'] ?? [];
        if ($personalAgent !== null) {
            $agentLogins = array_values(array_filter(
                $agentLogins,
                static fn (mixed $login): bool => strcasecmp((string) $login, $personalAgent) === 0,
            ));
        }
        $mappedUsers = $this->usersForViciLogins($agentLogins);
        $records = array_map(function (HistoricalCallRecord $record) use ($mappedUsers, $dispositionData): HistoricalCallRecord {
            $login = strtolower(trim((string) $record->vicidialUser));
            $user = $login !== '' ? ($mappedUsers[$login] ?? null) : null;
            $status = strtoupper(trim($record->status));
            $disposition = $dispositionData['by_status'][$status] ?? null;

            return $record
                ->withCrmUser($user)
                ->withDisposition($disposition['code'] ?? null, $disposition['label'] ?? 'Unmapped');
        }, $providerResult->records);

        $filterOptions['agents'] = $this->agentOptions($agentLogins, $mappedUsers);
        $filterOptions['statuses'] = $providerResult->filterOptions['statuses'] ?? [];
        $filterOptions['campaigns'] = $selectedCampaignCodes;
        $paginator = new LengthAwarePaginator(
            $records,
            $providerResult->total,
            $perPage,
            $page,
            ['path' => function_exists('url') ? url()->current() : ''],
        );
        if (function_exists('request') && request()->hasAny(array_keys(request()->query()))) {
            $paginator->appends(request()->query());
        }

        $sourceHealth = array_merge($providerResult->meta, [
            'status' => 'healthy',
            'availability' => 'available',
            'source' => 'vicidial_database',
        ]);

        return new HistoricalCallHistoryPage(
            available: true,
            state: $providerResult->total > 0 ? 'data' : 'confirmed_empty',
            records: $paginator,
            filterOptions: $filterOptions,
            scope: $scopeData,
            sourceHealth: $sourceHealth,
            message: null,
        );
    }

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

    public function getCallSessionsForAgent(
        User $user,
        string $campaignCode,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $phone = null,
        ?string $status = null,
        int $perPage = 15,
    ) {
        $q = CallSession::with(['campaign', 'user'])
            ->where('campaign_code', $campaignCode)
            ->where('user_id', $user->id)
            ->orderByDesc('dialed_at')
            ->orderByDesc('created_at');

        $this->applyCallSessionFilters($q, $startDate, $endDate, $phone, $status);

        return $q->paginate($perPage);
    }

    public function getCallSessionsForCampaign(
        string $campaignCode,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $agent = null,
        ?string $phone = null,
        ?string $status = null,
        int $perPage = 25,
    ) {
        $q = CallSession::with(['campaign', 'user'])
            ->where('campaign_code', $campaignCode)
            ->orderByDesc('dialed_at')
            ->orderByDesc('created_at');

        $this->applyCallSessionFilters($q, $startDate, $endDate, $phone, $status);

        if ($agent) {
            $q->whereHas('user', function ($query) use ($agent) {
                $query->where('full_name', 'like', '%'.$agent.'%')
                    ->orWhere('name', 'like', '%'.$agent.'%')
                    ->orWhere('username', 'like', '%'.$agent.'%');
            });
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

    protected function applyCallSessionFilters($q, ?string $startDate, ?string $endDate, ?string $phone, ?string $status): void
    {
        if ($startDate) {
            $q->whereDate('dialed_at', '>=', $startDate);
        }
        if ($endDate) {
            $q->whereDate('dialed_at', '<=', $endDate);
        }
        if ($phone) {
            $q->where('phone_number', 'like', '%'.$phone.'%');
        }
        if ($status) {
            $q->where('status', $status);
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $filterOptions
     * @param  array<string, mixed>  $sourceMeta
     */
    protected function unavailableHistoricalPage(
        array $scope,
        array $filterOptions,
        string $message,
        string $classification,
        array $sourceMeta = [],
        int $perPage = 25,
    ): HistoricalCallHistoryPage {
        $paginator = new LengthAwarePaginator([], 0, min(100, max(1, $perPage)), 1, [
            'path' => function_exists('url') ? url()->current() : '',
        ]);
        $sourceHealth = array_merge($sourceMeta, [
            'status' => 'unavailable',
            'availability' => 'unavailable',
            'classification' => $classification,
            'source' => 'vicidial_database',
        ]);

        return new HistoricalCallHistoryPage(
            available: false,
            state: 'unavailable',
            records: $paginator,
            filterOptions: $filterOptions,
            scope: $scope,
            sourceHealth: $sourceHealth,
            message: $message,
        );
    }

    /**
     * @return array{by_status: array<string, array{code: string, label: string}>, options: array<string, string>}
     */
    protected function dispositionData(string $campaignCode): array
    {
        $codes = DispositionCode::query()
            ->active()
            ->where(function ($query) use ($campaignCode): void {
                $query->where('campaign_code', $campaignCode)->orWhere('campaign_code', '');
            })
            ->ordered()
            ->get();
        $byCode = [];
        foreach ($codes as $code) {
            $key = strtoupper((string) $code->code);
            if ((string) $code->campaign_code === $campaignCode || ! isset($byCode[$key])) {
                $byCode[$key] = $code;
            }
        }

        $byStatus = [];
        $options = [];
        foreach ($byCode as $code) {
            $status = strtoupper((string) (config('vicidial.disposition_map.'.strtoupper((string) $code->code)) ?? $code->code));
            $entry = ['code' => (string) $code->code, 'label' => (string) $code->label];
            $byStatus[$status] = $entry;
            $options[(string) $code->code] = (string) $code->label;
        }

        return ['by_status' => $byStatus, 'options' => $options];
    }

    /**
     * @param  array{by_status: array<string, array{code: string, label: string}>, options: array<string, string>}  $dispositionData
     * @return array<int, string>
     */
    protected function resolveDispositionFilter(mixed $requested, array $dispositionData): array
    {
        $requested = trim((string) $requested);
        if ($requested === '') {
            return [];
        }

        foreach ($dispositionData['by_status'] as $status => $disposition) {
            if (strcasecmp($requested, $status) === 0
                || strcasecmp($requested, $disposition['code']) === 0
                || strcasecmp($requested, $disposition['label']) === 0) {
                return [$status];
            }
        }

        return ['__no_matching_disposition__'];
    }

    /**
     * @param  array<int, string>  $logins
     * @return array<string, User>
     */
    protected function usersForViciLogins(array $logins): array
    {
        $logins = array_values(array_filter(array_map(
            static fn (mixed $login): string => trim((string) $login),
            $logins,
        )));
        if ($logins === []) {
            return [];
        }

        $users = User::withTrashed()
            ->where(function ($query) use ($logins): void {
                foreach (array_unique($logins) as $login) {
                    $query->orWhereRaw('LOWER(vici_user) = ?', [strtolower($login)]);
                }
            })
            ->get();

        $mapped = [];
        foreach ($users as $user) {
            $key = strtolower(trim((string) $user->vici_user));
            if ($key !== '' && ! isset($mapped[$key])) {
                $mapped[$key] = $user;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<int, string>  $logins
     * @param  array<string, User>  $mappedUsers
     * @return array<int, array{value: string, label: string}>
     */
    protected function agentOptions(array $logins, array $mappedUsers): array
    {
        return array_map(function (string $login) use ($mappedUsers): array {
            $user = $mappedUsers[strtolower($login)] ?? null;
            $label = $user
                ? (string) ($user->full_name ?: $user->name ?: $user->username ?: $login)
                : $login.' (CRM user unavailable)';

            return ['value' => $login, 'label' => $label];
        }, $logins);
    }
}
