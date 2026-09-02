<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\DispositionCode;
use App\Models\TelephonyCallHistory;
use App\Models\User;
use App\Models\VicidialCallHistorySyncState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Throwable;

class LocalCallHistoryQueryService
{
    public function __construct(
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPage(
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

        if (! $scope->campaign->exists || $scope->server === null || $campaignCodes === []) {
            return $this->unavailablePage(
                $scopeData,
                $filterOptions,
                $scope->server === null
                    ? "No VICIdial server is configured for campaign '{$campaignCode}'."
                    : "No permitted VICIdial campaigns are mapped to CRM campaign '{$campaignCode}'.",
                $scope->server === null ? 'NOT_CONFIGURED' : 'NO_CAMPAIGNS_MAPPED',
                $perPage,
            );
        }

        $requestedCampaign = trim((string) ($filters['vicidial_campaign'] ?? ''));
        $selectedCampaignCodes = $scope->narrowCampaignCodes($requestedCampaign, true);
        if ($requestedCampaign !== '' && $selectedCampaignCodes === []) {
            return $this->unavailablePage(
                $scopeData,
                $filterOptions,
                'The selected VICIdial campaign is not mapped to this CRM campaign.',
                'UNAUTHORIZED_SCOPE',
                $perPage,
            );
        }

        $dispositionData = $this->dispositionData($campaignCode);
        $filterOptions['dispositions'] = $dispositionData['options'];
        $personalAgent = null;
        if ($personal) {
            $personalAgent = trim((string) ($viewer->vici_user ?? ''));
            if ($personalAgent === '') {
                return $this->unavailablePage(
                    $scopeData,
                    $filterOptions,
                    'Your VICIdial user mapping is not configured, so personal Call History is unavailable.',
                    'AGENT_MAPPING_NOT_CONFIGURED',
                    $perPage,
                );
            }
            $filters['agent'] = $personalAgent;
        }

        $timezone = (string) config('vicidial.report_timezone', config('app.timezone', 'UTC'));
        $startDate = $this->dateFilter($filters['start_date'] ?? null) ?? Carbon::now($timezone)->toDateString();
        $endDate = $this->dateFilter($filters['end_date'] ?? null) ?? $startDate;
        $baseQuery = TelephonyCallHistory::query()
            ->with('crmUser')
            ->where('vicidial_server_id', $scope->server->getKey())
            ->where('crm_campaign_id', $scope->campaign->getKey())
            ->whereIn('vicidial_campaign_id', $selectedCampaignCodes)
            ->where('call_date', '>=', $startDate.' 00:00:00')
            ->where('call_date', '<=', $endDate.' 23:59:59');

        $filterOptions['agents'] = $this->agentOptions($baseQuery->clone()->pluck('vicidial_user')->filter()->unique()->sort()->values()->all());
        $filterOptions['statuses'] = $baseQuery->clone()->pluck('status')->filter()->map(fn (mixed $status): string => (string) $status)->unique()->sort()->values()->all();
        $filterOptions['campaigns'] = $selectedCampaignCodes;

        $query = $baseQuery->clone();
        $dispositionStatuses = $this->resolveDispositionFilter($filters['disposition'] ?? null, $dispositionData);
        if ($dispositionStatuses !== []) {
            $query->whereIn('status', $dispositionStatuses);
        } elseif (trim((string) ($filters['status'] ?? '')) !== '') {
            $query->where('status', trim((string) $filters['status']));
        }
        if (($agent = trim((string) ($filters['agent'] ?? ''))) !== '') {
            $query->whereRaw('LOWER(vicidial_user) = ?', [strtolower($agent)]);
        }
        if (($phone = trim((string) ($filters['phone'] ?? ''))) !== '') {
            $query->where(function (Builder $phoneQuery) use ($phone): void {
                foreach ($this->phoneVariants($phone) as $variant) {
                    $phoneQuery->orWhere('phone_number', 'like', '%'.$variant.'%');
                }
            });
        }
        if (($direction = strtoupper(trim((string) ($filters['direction'] ?? '')))) !== '' && in_array($direction, ['INBOUND', 'OUTBOUND'], true)) {
            $query->where('direction', $direction);
        }
        if (isset($filters['lead_id']) && (int) $filters['lead_id'] > 0) {
            $query->where('lead_id', (int) $filters['lead_id']);
        }

        $sort = (string) ($filters['sort'] ?? 'called_at');
        $sortColumn = match ($sort) {
            'agent' => 'vicidial_user',
            'duration' => 'duration_seconds',
            'status' => 'status',
            'vicidial_campaign' => 'vicidial_campaign_id',
            default => 'call_date',
        };
        $sortDirection = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumn, $sortDirection)->orderBy('call_date', $sortDirection)->orderBy('id', $sortDirection);

        try {
            $total = (clone $query)->count();
            $page = max(1, (int) ($filters['page'] ?? Paginator::resolveCurrentPage('page')));
            $perPage = min(100, max(1, $perPage));
            $records = $query->forPage($page, $perPage)->get()->map(
                fn (TelephonyCallHistory $history): HistoricalCallRecord => $this->toRecord($history, $scope->campaign, $dispositionData),
            )->values()->all();
            $paginator = new LengthAwarePaginator(
                $records,
                $total,
                $perPage,
                $page,
                ['path' => function_exists('url') ? url()->current() : ''],
            );
            if (function_exists('request') && request()->hasAny(array_keys(request()->query()))) {
                $paginator->appends(request()->query());
            }

            $health = $this->sourceHealth($scope->server->getKey(), $scope->campaign->getKey(), $total);
            $state = $total > 0 ? 'data' : ($health['sync_status'] === VicidialCallHistorySyncState::STATUS_HEALTHY ? 'confirmed_empty' : 'syncing');
            if ($health['status'] === 'stale' && $total === 0) {
                $state = 'stale';
            }

            return new HistoricalCallHistoryPage(
                available: true,
                state: $state,
                records: $paginator,
                filterOptions: $filterOptions,
                scope: $scopeData,
                sourceHealth: $health,
                message: $health['status'] === 'stale' ? 'Showing locally stored data while the latest VICIdial synchronization is unavailable.' : null,
            );
        } catch (Throwable) {
            return $this->unavailablePage(
                $scopeData,
                $filterOptions,
                'Local Call History storage is currently unavailable. Please try again.',
                'LOCAL_DATABASE_ERROR',
                $perPage,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function syncHealth(string $campaignCode): array
    {
        $scope = $this->scopeResolver->resolve($campaignCode);
        if ($scope->server === null || ! $scope->campaign->exists) {
            return [
                'status' => 'unavailable',
                'availability' => 'unavailable',
                'sync_status' => 'not_configured',
                'classification' => 'NOT_CONFIGURED',
                'source' => 'local_database',
            ];
        }

        return $this->sourceHealth($scope->server->getKey(), $scope->campaign->getKey());
    }

    private function toRecord(TelephonyCallHistory $history, Campaign $campaign, array $dispositionData): HistoricalCallRecord
    {
        $status = strtoupper(trim((string) $history->status));
        $disposition = $dispositionData['by_status'][$status] ?? null;
        $user = $history->crmUser;
        $agentName = $user
            ? (string) ($user->full_name ?: $user->name ?: $user->username ?: $history->vicidial_user ?: 'CRM user')
            : ((string) ($history->vicidial_user ?: 'Unknown agent'));

        return new HistoricalCallRecord(
            id: $history->source_table.':'.$history->source_unique_id,
            uniqueCallId: $history->source_unique_id,
            crmCampaignId: (int) $campaign->getKey(),
            crmCampaignCode: (string) $campaign->code,
            vicidialCampaignId: (string) ($history->vicidial_campaign_id ?? ''),
            vicidialListId: $history->vicidial_list_id,
            leadId: $history->lead_id,
            vicidialUser: $history->vicidial_user,
            crmUserId: $history->crm_user_id,
            crmUserName: $user ? $agentName : null,
            agentDisplayName: $agentName,
            phoneNumber: $history->phone_number,
            callDate: $history->call_date,
            callStartedAt: $history->call_started_at,
            callEndedAt: $history->call_ended_at,
            callDirection: (string) $history->direction,
            status: (string) ($history->status ?? ''),
            dispositionCode: $disposition['code'] ?? $history->disposition_code,
            dispositionLabel: $disposition['label'] ?? (string) ($history->disposition_label ?: 'Unmapped'),
            durationSeconds: $history->duration_seconds,
            talkSeconds: $history->talk_seconds,
            waitSeconds: $history->wait_seconds,
            rawEndReason: $history->raw_end_reason,
            sourceTable: $history->source_table,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function sourceHealth(int $serverId, int $campaignId, ?int $total = null): array
    {
        $state = VicidialCallHistorySyncState::query()->forScope($serverId, $campaignId)->first();
        $lastSuccess = $state?->last_successful_sync_at;
        $staleAfter = max(1, (int) config('vicidial.call_history_sync.stale_after_minutes', 5));
        $isStale = $state === null
            || $state->status === VicidialCallHistorySyncState::STATUS_FAILED
            || $lastSuccess === null
            || $lastSuccess->lt(now()->subMinutes($staleAfter));

        return [
            'source' => 'local_database',
            'availability' => 'available',
            'status' => $isStale ? 'stale' : 'healthy',
            'sync_status' => $state?->status ?? VicidialCallHistorySyncState::STATUS_NEVER_SYNCED,
            'stale' => $isStale,
            'last_successful_sync_at' => $lastSuccess?->toIso8601String(),
            'last_failed_at' => $state?->last_failed_at?->toIso8601String(),
            'last_call_at' => $state?->last_call_at?->toIso8601String(),
            'last_error_classification' => $state?->last_error_classification,
            'last_error_message' => $state?->last_error_message,
            'last_rows_received' => $state?->last_rows_received ?? 0,
            'last_rows_inserted' => $state?->last_rows_inserted ?? 0,
            'last_rows_updated' => $state?->last_rows_updated ?? 0,
            'total_local_records' => $total ?? TelephonyCallHistory::query()
                ->where('vicidial_server_id', $serverId)
                ->where('crm_campaign_id', $campaignId)
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $filterOptions
     */
    protected function unavailablePage(array $scope, array $filterOptions, string $message, string $classification, int $perPage): HistoricalCallHistoryPage
    {
        $paginator = new LengthAwarePaginator([], 0, min(100, max(1, $perPage)), 1, [
            'path' => function_exists('url') ? url()->current() : '',
        ]);

        return new HistoricalCallHistoryPage(
            available: false,
            state: 'unavailable',
            records: $paginator,
            filterOptions: $filterOptions,
            scope: $scope,
            sourceHealth: [
                'source' => 'local_database',
                'availability' => 'unavailable',
                'status' => 'unavailable',
                'classification' => $classification,
            ],
            message: $message,
        );
    }

    /**
     * @param  array<int, string>  $logins
     * @return array<int, array{value: string, label: string}>
     */
    protected function agentOptions(array $logins): array
    {
        $logins = array_values(array_unique(array_filter(array_map(
            static fn (mixed $login): string => trim((string) $login),
            $logins,
        ))));
        if ($logins === []) {
            return [];
        }

        $users = User::withTrashed()->where(function ($query) use ($logins): void {
            foreach ($logins as $login) {
                $query->orWhereRaw('LOWER(vici_user) = ?', [strtolower($login)]);
            }
        })->get()->keyBy(fn (User $user): string => strtolower(trim((string) $user->vici_user)));

        return array_map(function (string $login) use ($users): array {
            $user = $users->get(strtolower($login));
            $label = $user
                ? (string) ($user->full_name ?: $user->name ?: $user->username ?: $login)
                : $login.' (CRM user unavailable)';

            return ['value' => $login, 'label' => $label];
        }, $logins);
    }

    /**
     * @return array{by_status: array<string, array{code: string, label: string}>, options: array<string, string>}
     */
    protected function dispositionData(string $campaignCode): array
    {
        $codes = DispositionCode::query()->active()->where(function ($query) use ($campaignCode): void {
            $query->where('campaign_code', $campaignCode)->orWhere('campaign_code', '');
        })->ordered()->get();
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
     * @param  array{by_status: array<string, array{code: string, label: string}>}  $data
     * @return array<int, string>
     */
    protected function resolveDispositionFilter(mixed $requested, array $data): array
    {
        $requested = trim((string) $requested);
        if ($requested === '') {
            return [];
        }
        foreach ($data['by_status'] as $status => $disposition) {
            if (strcasecmp($requested, $status) === 0 || strcasecmp($requested, $disposition['code']) === 0 || strcasecmp($requested, $disposition['label']) === 0) {
                return [$status];
            }
        }

        return ['__no_matching_disposition__'];
    }

    protected function dateFilter(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    protected function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [$phone];
        }
        $variants = [$digits];
        if (str_starts_with($digits, '09')) {
            $variants[] = '63'.substr($digits, 1);
            $variants[] = substr($digits, 1);
        } elseif (str_starts_with($digits, '639')) {
            $variants[] = '0'.substr($digits, 2);
            $variants[] = substr($digits, 2);
        } elseif (str_starts_with($digits, '9')) {
            $variants[] = '0'.$digits;
            $variants[] = '63'.$digits;
        }

        return array_values(array_unique($variants));
    }
}
