<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\CampaignDispositionRecord;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Models\VicidialServer;
use App\Services\CampaignService;
use App\Support\OperationResult;
use App\Telephony\AgentOperationalState;
use App\Telephony\QueueHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupervisorOperationalService
{
    public function __construct(
        protected CampaignService $campaignService,
        protected \App\Repositories\VicidialServerRepository $serverRepository,
        protected ReportingService $reportingService,
    ) {}

    /**
     * Build a campaign-scoped, current-state Supervisor snapshot.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Request $request): array
    {
        $today = Carbon::today();
        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaign($request, $campaigns);
        $campaignConfig = $this->campaignService->getCampaign($campaign);
        $server = $campaign !== '' ? $this->serverRepository->getForCampaign($campaign) : null;
        $remoteSnapshot = $this->fetchRemoteSnapshot($request, $campaign, $server, $today);
        $remoteAgents = $remoteSnapshot['agents'];
        $remoteAgentMetrics = $remoteSnapshot['agent_metrics'];

        $users = User::query()
            ->whereIn('role', ['Agent', 'Team Leader'])
            ->where(function ($query) use ($campaign, $today): void {
                $query->where('default_campaign', $campaign)
                    ->orWhereHas('vicidialSessions', function ($sessionQuery) use ($campaign): void {
                        $sessionQuery->where('campaign_code', $campaign);
                    })
                    ->orWhereHas('callSessions', function ($callQuery) use ($campaign, $today): void {
                        $callQuery->where('campaign_code', $campaign)
                            ->whereDate('dialed_at', $today);
                    });
            })
            ->with(['attendanceLogs' => function ($query) use ($today): void {
                $query->whereDate('event_time', $today)->orderByDesc('event_time');
            }])
            ->get();

        if ($remoteAgents !== [] || $remoteAgentMetrics !== []) {
            $remoteUserIds = array_values(array_unique([
                ...array_keys($remoteAgents),
                ...array_keys($remoteAgentMetrics),
            ]));
            $users = User::query()
                ->whereIn('role', ['Agent', 'Team Leader'])
                ->whereIn('id', array_values(array_unique([
                    ...$users->pluck('id')->all(),
                    ...$remoteUserIds,
                ])))
                ->with(['attendanceLogs' => function ($query) use ($today): void {
                    $query->whereDate('event_time', $today)->orderByDesc('event_time');
                }])
                ->get();
        }

        $userIds = $users->pluck('id')->all();
        $agentNames = $users->keyBy('id')->map(
            fn (User $user): string => $user->full_name ?? $user->username ?? (string) $user->id,
        )->all();

        $activeCallRecords = CallSession::query()
            ->whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->active()
            ->get();
        $activeCalls = $activeCallRecords->keyBy('user_id');

        $todayCalls = CallSession::query()
            ->whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->whereDate('dialed_at', $today)
            ->get([
                'user_id',
                'status',
                'dialed_at',
                'answered_at',
                'ended_at',
                'call_duration_seconds',
            ]);
        $todayCallMetrics = $todayCalls
            ->groupBy('user_id')
            ->map(fn (Collection $calls): array => $this->summarizeCalls($calls));

        $todaysDispositions = CampaignDispositionRecord::query()
            ->whereIn('agent', array_values($agentNames))
            ->where('campaign_code', $campaign)
            ->whereDate('called_at', $today)
            ->select('agent', DB::raw('COUNT(*) as total'))
            ->groupBy('agent')
            ->pluck('total', 'agent');

        $viciSessions = VicidialAgentSession::query()
            ->whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->get()
            ->keyBy('user_id');

        $agents = $users->map(function (User $user) use (
            $campaign,
            $activeCalls,
            $todayCallMetrics,
            $todaysDispositions,
            $agentNames,
            $viciSessions,
            $remoteAgents,
            $remoteAgentMetrics,
        ): array {
            $latestLog = $user->attendanceLogs->first();
            $isOnline = $latestLog?->event_type === 'login';
            $currentCall = $activeCalls->get($user->id);
            $agentName = $agentNames[$user->id] ?? $user->username;
            $remote = $remoteAgents[$user->id] ?? null;
            $remoteMetrics = $remoteAgentMetrics[$user->id] ?? null;
            $viciSession = $viciSessions->get($user->id);
            $dispositions = (int) $todaysDispositions->get($agentName, 0);

            if ($remote !== null) {
                $state = AgentOperationalState::from($remote['state']);
            } elseif ($currentCall) {
                $state = AgentOperationalState::OnCall;
            } elseif ($isOnline) {
                $state = AgentOperationalState::Available;
            } elseif ($viciSession !== null) {
                $state = $this->stateFromRemoteAgent(
                    (string) ($viciSession->session_status ?? ''),
                    (string) ($viciSession->pause_code ?? ''),
                );
            } else {
                $state = AgentOperationalState::Offline;
            }

            $callMetrics = $todayCallMetrics->get($user->id, [
                'terminal' => 0,
                'answered_terminal' => 0,
                'wait_seconds' => 0,
                'wait_samples' => 0,
                'handle_seconds' => 0,
                'handle_samples' => 0,
            ]);
            $crmAverageHandle = $callMetrics['handle_samples'] > 0
                ? round($callMetrics['handle_seconds'] / $callMetrics['handle_samples'], 1)
                : null;
            $callsToday = $remote['calls_today'] ?? $remoteMetrics['calls_today'] ?? $callMetrics['terminal'];
            $averageHandle = $remoteMetrics['avg_handle'] ?? $crmAverageHandle;
            $averageWait = $remoteMetrics['avg_wait'] ?? (
                $callMetrics['wait_samples'] > 0
                    ? round($callMetrics['wait_seconds'] / $callMetrics['wait_samples'], 1)
                    : null
            );
            $currentCallDuration = $currentCall?->answered_at
                ? (int) now()->diffInSeconds($currentCall->answered_at)
                : null;
            $stateDuration = $remote['state_duration_seconds'] ?? null;

            return [
                'id' => $user->id,
                'name' => $agentName,
                'campaign_code' => $campaign,
                'state' => $state->value,
                'state_label' => $state->label(),
                'status' => $this->legacyStatus($state),
                'status_label' => $this->legacyStatusLabel($state),
                'calls_today' => $callsToday,
                'calls_since_login' => $callsToday,
                'avg_handle' => $averageHandle,
                'avg_wait' => $averageWait,
                'dispositions' => $dispositions,
                'since' => $latestLog?->event_time?->format('H:i') ?? '—',
                'state_duration_seconds' => $stateDuration,
                'idle_seconds' => $state === AgentOperationalState::Available ? $stateDuration : null,
                'last_call_at' => null,
                'current_call' => $currentCall ? [
                    'phone_number' => $currentCall->phone_number,
                    'status' => $currentCall->status,
                    'duration' => $currentCallDuration,
                ] : null,
                'vici_status' => $remote['status'] ?? $viciSession?->session_status,
                'vici_sub_status' => $remote['sub_status'] ?? null,
                'queue_count' => $remote['queue_count'] ?? $viciSession?->last_status_payload['queue_count'] ?? null,
            ];
        })->values();

        $crmTotalToday = (int) $todayCallMetrics->sum('terminal');
        $crmAnsweredToday = (int) $todayCallMetrics->sum('answered_terminal');
        $waitSamples = (int) $todayCallMetrics->sum('wait_samples');
        $waitSeconds = (int) $todayCallMetrics->sum('wait_seconds');
        $handleSamples = (int) $todayCallMetrics->sum('handle_samples');
        $handleSeconds = (int) $todayCallMetrics->sum('handle_seconds');
        $crmAnswerRate = $crmTotalToday > 0 ? round(($crmAnsweredToday / $crmTotalToday) * 100, 1) : 0;
        $crmCallsByHour = $todayCalls
            ->filter(fn (CallSession $call): bool => $call->dialed_at !== null)
            ->groupBy(fn (CallSession $call): string => $call->dialed_at->format('H'))
            ->map(fn (Collection $calls): int => $calls->count())
            ->all();

        $remoteCallStats = $remoteSnapshot['call_stats'];
        $remoteRealtimeStats = $remoteSnapshot['realtime_stats'];
        $remotePerformanceStats = $remoteSnapshot['performance_stats'];
        $totalToday = $remoteCallStats['total'] ?? $crmTotalToday;
        $answeredToday = $remoteCallStats['answered'] ?? $crmAnsweredToday;
        $answerRate = $remoteCallStats['answer_rate'] ?? $crmAnswerRate;
        $callsByHour = $remoteCallStats['calls_by_hour'] ?? $crmCallsByHour;
        $callsWaiting = $remoteRealtimeStats['calls_waiting'] ?? $this->fallbackQueueCount($agents);
        $agentsOnline = $remoteRealtimeStats['agents_online']
            ?? $this->countStates($agents, [
                AgentOperationalState::Available,
                AgentOperationalState::OnCall,
                AgentOperationalState::Paused,
                AgentOperationalState::Ringing,
                AgentOperationalState::Queue,
            ]);
        $agentsAvailable = $remoteRealtimeStats['agents_available']
            ?? $this->countStates($agents, [AgentOperationalState::Available]);
        $agentsOnCall = $remoteRealtimeStats['agents_in_calls']
            ?? $this->countStates($agents, [AgentOperationalState::OnCall, AgentOperationalState::Ringing]);
        $agentsPaused = $remoteRealtimeStats['agents_paused']
            ?? $this->countStates($agents, [AgentOperationalState::Paused]);
        $avgWait = $remoteRealtimeStats['avg_wait_seconds']
            ?? $remotePerformanceStats['avg_wait']
            ?? ($waitSamples > 0 ? round($waitSeconds / $waitSamples, 1) : null);
        $oldestWait = $remoteRealtimeStats['oldest_wait_seconds'] ?? null;
        $activeCallCount = $remoteRealtimeStats['agents_in_calls']
            ?? max($activeCallRecords->count(), $agentsOnCall);
        $avgHandle = $remotePerformanceStats['avg_handle']
            ?? ($handleSamples > 0 ? round($handleSeconds / $handleSamples, 1) : null);
        $queue = $this->queueSnapshot(
            $callsWaiting,
            $oldestWait,
            $agentsAvailable,
            $avgWait,
            $remoteRealtimeStats['abandoned_last_15m'] ?? null,
        );
        $queue['active_calls'] = $activeCallCount;

        return [
            'agents' => $agents,
            'stats' => [
                'agentsOnline' => $agentsOnline,
                'agentsAvailable' => $agentsAvailable,
                'agentsOnCall' => $agentsOnCall,
                'agentsPaused' => $agentsPaused,
                'callsWaiting' => $callsWaiting,
                'oldestWaitSeconds' => $oldestWait,
                'avgWaitTime' => $avgWait,
                'longestIdleSeconds' => $this->longestIdleSeconds($agents),
                'callsActive' => $activeCallCount,
                'avgHandleTime' => $avgHandle,
                'todayTotal' => $totalToday,
                'callsAnswered' => $answeredToday,
                'answerRate' => $answerRate,
                'callsByHour' => $callsByHour,
                'callSource' => $remoteCallStats !== null ? 'vicidial' : 'crm',
                'realtimeSource' => $remoteSnapshot['realtime_source'],
                'performanceSource' => $remoteSnapshot['performance_source'],
                'queue' => $queue,
                'updatedAt' => now()->toIso8601String(),
                'slaPercent' => $answerRate,
            ],
            'routing' => [
                'campaign_code' => $campaign,
                'campaign_name' => $campaignConfig['name'] ?? $request->session()->get('campaign_name', $campaign),
                'configured' => $server !== null,
                'server_name' => $server?->server_name,
                'reporting_status' => $remoteSnapshot['reporting_status'],
                'message' => $remoteSnapshot['reporting_message'],
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $campaigns
     */
    private function resolveCampaign(Request $request, array $campaigns): string
    {
        $requested = trim((string) $request->query('campaign', ''));
        if ($requested !== '' && isset($campaigns[$requested])) {
            return $requested;
        }
        $sessionCampaign = trim((string) $request->session()->get('campaign', ''));
        if ($sessionCampaign !== '' && isset($campaigns[$sessionCampaign])) {
            return $sessionCampaign;
        }

        return (string) array_key_first($campaigns);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRemoteSnapshot(
        Request $request,
        string $campaign,
        ?VicidialServer $server,
        Carbon $today,
    ): array {
        $empty = [
            'agents' => [],
            'agent_metrics' => [],
            'call_stats' => null,
            'realtime_stats' => null,
            'performance_stats' => null,
            'realtime_source' => 'crm',
            'performance_source' => 'crm',
            'reporting_status' => 'not_configured',
            'reporting_message' => null,
        ];
        if ($server === null) {
            $empty['reporting_message'] = "No VICIdial server is configured for campaign '{$campaign}'.";

            return $empty;
        }
        if (trim((string) $server->api_user) === '' || trim((string) $server->api_pass) === '') {
            $empty['reporting_message'] = 'VICIdial reports are not configured. Add an API user and password with View Reports access for this CRM campaign server.';

            return $empty;
        }

        $candidates = User::query()
            ->whereIn('role', ['Agent', 'Team Leader'])
            ->whereNotNull('vici_user')
            ->where('vici_user', '!=', '')
            ->get();
        $httpOptions = ['connect_timeout' => 1, 'timeout' => 3, 'retry_times' => 0];
        $reports = $this->reportingService->supervisorSnapshot(
            $request->user(),
            $campaign,
            $today->format('Y-m-d'),
            $httpOptions,
        );
        $loggedAgents = $this->parseLoggedAgentReport($reports['logged_agents'] ?? null, $candidates);
        $agentPerformance = $this->parseAgentPerformanceReport($reports['agent_performance'] ?? null, $candidates);
        $groups = array_values(array_unique(array_filter([
            ...$loggedAgents['user_groups'],
            ...$agentPerformance['user_groups'],
        ], static fn (string $group): bool => $group !== '')));
        $realtimeStats = $loggedAgents['stats'];
        $realtimeSource = $loggedAgents['available']
            ? ($this->hasCompleteRealtimeStats($realtimeStats) ? 'vicidial' : 'mixed')
            : 'crm';
        $groupResult = null;
        if ($groups !== []) {
            $groupResult = $this->reportingService->userGroupStatus(
                $request->user(),
                $campaign,
                implode('|', $groups),
                $httpOptions,
            );
            $groupStats = $this->parseUserGroupStatus($groupResult);
            if ($groupStats !== null) {
                $realtimeStats = array_merge($realtimeStats ?? [], $groupStats);
                $realtimeSource = $this->hasCompleteRealtimeStats($realtimeStats) ? 'vicidial' : 'mixed';
            }
        }
        $health = $this->reportingHealth([
            $reports['logged_agents'] ?? null,
            $reports['agent_performance'] ?? null,
            $reports['call_totals'] ?? null,
            $groupResult,
        ]);

        return [
            'agents' => $loggedAgents['agents'],
            'agent_metrics' => $agentPerformance['agents'],
            'call_stats' => $this->parseRemoteCallStats($reports['call_totals'] ?? null),
            'realtime_stats' => $realtimeStats,
            'performance_stats' => $agentPerformance['stats'],
            'realtime_source' => $realtimeSource,
            'performance_source' => $this->performanceSource($agentPerformance['stats']),
            'reporting_status' => $health['status'],
            'reporting_message' => $health['message'],
        ];
    }

    /**
     * @param  array<int, OperationResult|null>  $results
     * @return array{status: string, message: string|null}
     */
    private function reportingHealth(array $results): array
    {
        $reports = array_values(array_filter($results, static fn (mixed $result): bool => $result instanceof OperationResult));
        $failed = array_values(array_filter($reports, static fn (OperationResult $result): bool => ! $result->success));
        if ($failed === []) {
            return ['status' => 'live', 'message' => null];
        }
        $guidance = 'Verify this CRM campaign server\'s API URL and network access, then confirm its API user has View Reports permission (levels 7/8).';
        if (count($failed) === count($reports)) {
            return ['status' => 'unavailable', 'message' => 'VICIdial reports are unavailable. '.$guidance];
        }

        return ['status' => 'degraded', 'message' => 'Some VICIdial reports are unavailable, so CRM fallback metrics may be incomplete. '.$guidance];
    }

    /**
     * @param  array{avg_handle?: float|int, avg_wait?: float|int}|null  $stats
     */
    private function performanceSource(?array $stats): string
    {
        if ($stats === null) {
            return 'crm';
        }

        return isset($stats['avg_handle'], $stats['avg_wait']) ? 'vicidial' : 'mixed';
    }

    /**
     * @param  Collection<int, User>  $candidates
     * @return array<string, mixed>
     */
    private function parseLoggedAgentReport(?OperationResult $result, Collection $candidates): array
    {
        $table = $this->parseRemoteTable($result);
        $empty = ['available' => false, 'agents' => [], 'user_groups' => [], 'stats' => null];
        if ($table === null || ! $this->remoteTableHasAnyHeader($table, ['user', 'agent_user', 'username'])) {
            return $empty;
        }
        $candidatesByViciUser = $candidates->keyBy(
            fn (User $user): string => strtolower(trim((string) $user->vici_user)),
        );
        $remoteAgents = [];
        $userGroups = [];
        $stats = ['agents_online' => 0, 'agents_available' => 0, 'agents_in_calls' => 0, 'agents_paused' => 0];
        $hasQueueCount = $this->remoteTableHasAnyHeader($table, ['queue_count', 'calls_waiting', 'queue']);
        if ($hasQueueCount) {
            $stats['calls_waiting'] = 0;
        }

        foreach ($table['rows'] as $row) {
            $status = trim((string) $this->remoteRowValue($row, ['status', 'agent_status', 'state']));
            $subStatus = trim((string) $this->remoteRowValue($row, ['sub_status', 'real_time_sub_status']));
            $userGroup = trim((string) $this->remoteRowValue($row, ['user_group', 'group']));
            $state = $this->stateFromRemoteAgent($status, $subStatus);
            if ($userGroup !== '') {
                $userGroups[] = $userGroup;
            }
            $stats['agents_online']++;
            if ($state === AgentOperationalState::Available || $state === AgentOperationalState::Queue) {
                $stats['agents_available']++;
            } elseif ($state === AgentOperationalState::OnCall || $state === AgentOperationalState::Ringing) {
                $stats['agents_in_calls']++;
            } elseif ($state === AgentOperationalState::Paused) {
                $stats['agents_paused']++;
            }
            $queueCount = $this->nonNegativeInteger($this->remoteRowValue($row, ['queue_count', 'calls_waiting', 'queue']));
            if ($hasQueueCount) {
                $stats['calls_waiting'] = max($stats['calls_waiting'], $queueCount ?? 0);
            }
            $viciUser = strtolower(trim((string) $this->remoteRowValue($row, ['user', 'agent_user', 'username'])));
            $user = $candidatesByViciUser->get($viciUser);
            if (! $user) {
                continue;
            }
            $remoteAgents[$user->id] = [
                'status' => $status,
                'sub_status' => $subStatus,
                'state' => $state->value,
                'user_group' => $userGroup,
                'queue_count' => $queueCount,
                'calls_today' => $this->nonNegativeInteger($this->remoteRowValue($row, ['calls_today', 'calls', 'total_calls'])),
                'state_duration_seconds' => $this->parseRemoteSeconds($this->remoteRowValue($row, ['status_seconds', 'state_duration', 'pause_seconds', 'duration'])),
            ];
        }

        $this->addOperationalFields($stats, $table);

        return ['available' => true, 'agents' => $remoteAgents, 'user_groups' => array_values(array_unique($userGroups)), 'stats' => $stats];
    }

    /**
     * @param  Collection<int, User>  $candidates
     * @return array<string, mixed>
     */
    private function parseAgentPerformanceReport(?OperationResult $result, Collection $candidates): array
    {
        $table = $this->parseRemoteTable($result);
        if ($table === null || ! $this->remoteTableHasAnyHeader($table, ['user', 'agent_user', 'username'])) {
            return ['agents' => [], 'user_groups' => [], 'stats' => null];
        }
        $candidatesByViciUser = $candidates->keyBy(
            fn (User $user): string => strtolower(trim((string) $user->vici_user)),
        );
        $remoteAgents = [];
        $userGroups = [];
        $totalTalkSeconds = 0;
        $totalWaitSeconds = 0;
        $talkSamples = 0;
        $waitSamples = 0;
        foreach ($table['rows'] as $row) {
            $calls = $this->nonNegativeInteger($this->remoteRowValue($row, ['calls', 'calls_today', 'total_calls']));
            if ($calls === null) {
                continue;
            }
            $userGroup = trim((string) $this->remoteRowValue($row, ['user_group', 'group']));
            if ($userGroup !== '') {
                $userGroups[] = $userGroup;
            }
            $averageTalk = $this->parseRemoteSeconds($this->remoteRowValue($row, ['avg_talk_time', 'average_talk_time']));
            $averageWait = $this->parseRemoteSeconds($this->remoteRowValue($row, ['avg_wait_time', 'average_wait_time']));
            $totalTalk = $this->parseRemoteSeconds($this->remoteRowValue($row, ['total_talk_time', 'talk_time']));
            $totalWait = $this->parseRemoteSeconds($this->remoteRowValue($row, ['total_wait_time', 'wait_time']));
            if ($calls > 0 && ($totalTalk !== null || $averageTalk !== null)) {
                $totalTalkSeconds += $totalTalk ?? ($averageTalk * $calls);
                $talkSamples += $calls;
            }
            if ($calls > 0 && ($totalWait !== null || $averageWait !== null)) {
                $totalWaitSeconds += $totalWait ?? ($averageWait * $calls);
                $waitSamples += $calls;
            }
            $viciUser = strtolower(trim((string) $this->remoteRowValue($row, ['user', 'agent_user', 'username'])));
            $user = $candidatesByViciUser->get($viciUser);
            if (! $user) {
                continue;
            }
            $remoteAgents[$user->id] = ['calls_today' => $calls, 'avg_handle' => $averageTalk, 'avg_wait' => $averageWait];
        }
        $stats = [];
        if ($talkSamples > 0) {
            $stats['avg_handle'] = round($totalTalkSeconds / $talkSamples, 1);
        }
        if ($waitSamples > 0) {
            $stats['avg_wait'] = round($totalWaitSeconds / $waitSamples, 1);
        }

        return ['agents' => $remoteAgents, 'user_groups' => array_values(array_unique($userGroups)), 'stats' => $stats !== [] ? $stats : null];
    }

    /**
     * @return array<string, int|float>|null
     */
    private function parseUserGroupStatus(OperationResult $result): ?array
    {
        $table = $this->parseRemoteTable($result);
        if ($table === null || $table['rows'] === []) {
            return null;
        }
        $fieldMap = [
            'agents_online' => ['agents_logged_in', 'agents_online'],
            'agents_available' => ['agents_waiting', 'agents_available'],
            'agents_in_calls' => ['agents_in_calls', 'agents_on_call'],
            'agents_paused' => ['agents_paused'],
            'calls_waiting' => ['calls_waiting', 'queue_count'],
            'oldest_wait_seconds' => ['oldest_wait', 'oldest_wait_seconds'],
            'avg_wait_seconds' => ['average_wait', 'avg_wait', 'avg_wait_seconds'],
            'abandoned_last_15m' => ['abandoned', 'abandoned_last_15m'],
        ];
        $stats = [];
        $validRows = 0;
        foreach ($table['rows'] as $row) {
            $rowIsValid = false;
            foreach ($fieldMap as $target => $sourceHeaders) {
                $value = $target === 'avg_wait_seconds'
                    ? $this->nonNegativeNumber($this->remoteRowValue($row, $sourceHeaders))
                    : $this->nonNegativeInteger($this->remoteRowValue($row, $sourceHeaders));
                if ($value === null) {
                    continue;
                }
                $stats[$target] = ($stats[$target] ?? 0) + $value;
                $rowIsValid = true;
            }
            if ($rowIsValid) {
                $validRows++;
            }
        }

        return $validRows > 0 ? $stats : null;
    }

    /**
     * @param  array<string, int|float>|null  $stats
     */
    private function hasCompleteRealtimeStats(?array $stats): bool
    {
        if ($stats === null) {
            return false;
        }
        foreach (['agents_online', 'agents_available', 'agents_in_calls', 'agents_paused', 'calls_waiting'] as $key) {
            if (! array_key_exists($key, $stats)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}|null
     */
    private function parseRemoteTable(?OperationResult $result): ?array
    {
        if ($result === null || ! $result->success) {
            return null;
        }
        $rows = array_values(array_filter((array) ($result->data['rows'] ?? []), 'is_array'));
        if ($rows === []) {
            return null;
        }
        $headers = array_map(fn (mixed $header): string => $this->normalizeRemoteHeader($header), $rows[0]);
        if (! array_filter($headers, static fn (string $header): bool => $header !== '')) {
            return null;
        }
        $dataRows = [];
        foreach (array_slice($rows, 1) as $row) {
            $data = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $data[$header] = trim((string) ($row[$index] ?? ''));
                }
            }
            $dataRows[] = $data;
        }

        return ['headers' => $headers, 'rows' => $dataRows];
    }

    /**
     * @param  array{headers: array<int, string>, rows: array<int, array<string, string>>}  $table
     * @param  array<int, string>  $headers
     */
    private function remoteTableHasAnyHeader(array $table, array $headers): bool
    {
        return (bool) array_intersect($headers, $table['headers']);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $headers
     */
    private function remoteRowValue(array $row, array $headers): mixed
    {
        foreach ($headers as $header) {
            if (array_key_exists($header, $row)) {
                return $row[$header];
            }
        }

        return null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 0) {
            return null;
        }

        return (int) $value;
    }

    private function nonNegativeNumber(mixed $value): float|int|null
    {
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (float) $value;
    }

    private function parseRemoteSeconds(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value >= 0 ? (int) round((float) $value) : null;
        }
        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2})$/', $value, $parts) === 1) {
            return ((int) $parts[1] * 3600) + ((int) $parts[2] * 60) + (int) $parts[3];
        }
        if (preg_match('/^(\d+):(\d{1,2})$/', $value, $parts) === 1) {
            return ((int) $parts[1] * 60) + (int) $parts[2];
        }

        return null;
    }

    private function normalizeRemoteHeader(mixed $header): string
    {
        $normalized = strtolower(trim((string) $header));

        return preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: '';
    }

    private function stateFromRemoteAgent(string $status, string $subStatus = ''): AgentOperationalState
    {
        $normalized = strtolower(trim($status.' '.$subStatus));

        return match (true) {
            $normalized === '' => AgentOperationalState::Unknown,
            str_contains($normalized, 'ring') => AgentOperationalState::Ringing,
            str_contains($normalized, 'incall'),
            str_contains($normalized, 'in call'),
            str_contains($normalized, 'active'),
            str_contains($normalized, 'dial'),
            str_contains($normalized, '3-way'),
            str_contains($normalized, 'park') => AgentOperationalState::OnCall,
            str_contains($normalized, 'pause'),
            str_contains($normalized, 'break') => AgentOperationalState::Paused,
            str_contains($normalized, 'queue') => AgentOperationalState::Queue,
            str_contains($normalized, 'ready'),
            str_contains($normalized, 'available'),
            str_contains($normalized, 'wait') => AgentOperationalState::Available,
            str_contains($normalized, 'dispo'),
            str_contains($normalized, 'dead'),
            str_contains($normalized, 'logout'),
            str_contains($normalized, 'offline') => AgentOperationalState::Offline,
            default => AgentOperationalState::Unknown,
        };
    }

    private function legacyStatus(AgentOperationalState $state): string
    {
        return match ($state) {
            AgentOperationalState::OnCall, AgentOperationalState::Ringing => 'oncall',
            AgentOperationalState::Available, AgentOperationalState::Queue => 'available',
            AgentOperationalState::Paused => 'break',
            AgentOperationalState::Offline => 'offline',
            default => 'unknown',
        };
    }

    private function legacyStatusLabel(AgentOperationalState $state): string
    {
        return match ($state) {
            AgentOperationalState::OnCall => 'On Call',
            AgentOperationalState::Ringing => 'Ringing',
            AgentOperationalState::Available => 'Available',
            AgentOperationalState::Queue => 'Queue',
            AgentOperationalState::Paused => 'On Break',
            AgentOperationalState::Offline => 'Offline',
            default => 'Unknown',
        };
    }

    /**
     * @param  array<string, int|float>  $stats
     * @param  array{headers: array<int, string>, rows: array<int, array<string, string>>}  $table
     */
    private function addOperationalFields(array &$stats, array $table): void
    {
        $fieldMap = [
            'oldest_wait_seconds' => ['oldest_wait', 'oldest_wait_seconds'],
            'avg_wait_seconds' => ['average_wait', 'avg_wait', 'avg_wait_seconds'],
            'abandoned_last_15m' => ['abandoned', 'abandoned_last_15m'],
        ];
        foreach ($fieldMap as $target => $headers) {
            foreach ($table['rows'] as $row) {
                $value = $target === 'avg_wait_seconds'
                    ? $this->nonNegativeNumber($this->remoteRowValue($row, $headers))
                    : $this->nonNegativeInteger($this->remoteRowValue($row, $headers));
                if ($value !== null) {
                    $stats[$target] = $target === 'oldest_wait_seconds'
                        ? max($stats[$target] ?? 0, $value)
                        : ($stats[$target] ?? 0) + $value;
                }
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $agents
     */
    private function fallbackQueueCount(Collection $agents): ?int
    {
        $values = $agents->pluck('queue_count')
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => max(0, (int) $value));

        return $values->isEmpty() ? null : (int) $values->max();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $agents
     * @param  array<int, AgentOperationalState>  $states
     */
    private function countStates(Collection $agents, array $states): int
    {
        $values = array_map(static fn (AgentOperationalState $state): string => $state->value, $states);

        return $agents->whereIn('state', $values)->count();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $agents
     */
    private function longestIdleSeconds(Collection $agents): ?int
    {
        $values = $agents->pluck('idle_seconds')
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => max(0, (int) $value));

        return $values->isEmpty() ? null : (int) $values->max();
    }

    /**
     * @return array<string, mixed>
     */
    private function queueSnapshot(
        ?int $callsWaiting,
        mixed $oldestWait,
        ?int $agentsAvailable,
        mixed $averageWait,
        mixed $abandoned,
    ): array {
        $oldestWait = is_numeric($oldestWait) ? max(0, (int) $oldestWait) : null;
        $averageWait = is_numeric($averageWait) ? max(0, round((float) $averageWait, 1)) : null;
        $abandoned = is_numeric($abandoned) ? max(0, (int) $abandoned) : null;
        $hasRequiredSignals = $callsWaiting !== null && $agentsAvailable !== null;
        $health = QueueHealth::Unknown;
        $reasons = [];
        $thresholds = config('vicidial.supervisor.queue', []);
        $minimumAvailableAgents = max(1, (int) ($thresholds['warning_no_available_agents'] ?? 1));
        if ($hasRequiredSignals) {
            if ($callsWaiting >= (int) ($thresholds['critical_waiting_calls'] ?? 10)
                || ($oldestWait !== null && $oldestWait >= (int) ($thresholds['critical_oldest_wait_seconds'] ?? 180))) {
                $health = QueueHealth::Critical;
                $reasons[] = 'Queue pressure is above the critical threshold.';
            } elseif ($callsWaiting >= (int) ($thresholds['warning_waiting_calls'] ?? 5)
                || ($oldestWait !== null && $oldestWait >= (int) ($thresholds['warning_oldest_wait_seconds'] ?? 60))
                || ($callsWaiting > 0 && $agentsAvailable < $minimumAvailableAgents)) {
                $health = QueueHealth::Warning;
                $reasons[] = 'Queue pressure needs supervisor attention.';
            } else {
                $health = QueueHealth::Healthy;
            }
        } else {
            $reasons[] = 'Required queue signals are unavailable.';
        }

        return [
            'health' => $health->value,
            'health_label' => $health->label(),
            'calls_waiting' => $callsWaiting,
            'oldest_wait_seconds' => $oldestWait,
            'available_agents' => $agentsAvailable,
            'active_calls' => null,
            'average_wait_last_15m' => $averageWait,
            'abandoned_last_15m' => $abandoned,
            'window_minutes' => 15,
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<string, int|float>|null
     */
    private function parseRemoteCallStats(?OperationResult $result): ?array
    {
        if ($result === null || ! $result->success) {
            return null;
        }
        $total = 0;
        $answered = 0;
        $validRows = 0;
        $callsByHour = [];
        foreach (array_values(array_filter((array) ($result->data['rows'] ?? []), 'is_array')) as $row) {
            if (! isset($row[1], $row[2]) || ! is_numeric($row[1]) || ! is_numeric($row[2])) {
                continue;
            }
            $validRows++;
            $total += max(0, (int) $row[1]);
            $answered += max(0, (int) $row[2]);
            foreach ($this->parseRemoteHourlyBreakdown((string) ($row[3] ?? '')) as $hour => $count) {
                $callsByHour[$hour] = ($callsByHour[$hour] ?? 0) + $count;
            }
        }
        if ($validRows === 0) {
            return null;
        }

        return [
            'total' => $total,
            'answered' => $answered,
            'answer_rate' => $total > 0 ? round(($answered / $total) * 100, 1) : 0,
            'calls_by_hour' => $callsByHour,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function parseRemoteHourlyBreakdown(string $breakdown): array
    {
        $result = [];
        foreach (explode(',', $breakdown) as $entry) {
            $parts = explode('-', trim($entry), 2);
            if (count($parts) !== 2 || ! preg_match('/^\d{1,2}$/', $parts[0]) || ! is_numeric($parts[1])) {
                continue;
            }
            $hour = (int) $parts[0];
            $count = (int) $parts[1];
            if ($hour < 0 || $hour > 23 || $count < 0) {
                continue;
            }
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $result[$key] = ($result[$key] ?? 0) + $count;
        }

        return $result;
    }

    /**
     * @param  Collection<int, CallSession>  $calls
     * @return array<string, int>
     */
    private function summarizeCalls(Collection $calls): array
    {
        $terminalCalls = $calls->filter(fn (CallSession $call): bool => $call->isTerminal());
        $answeredCalls = $calls->filter(fn (CallSession $call): bool => $call->answered_at !== null);
        $answeredTerminalCalls = $terminalCalls->filter(fn (CallSession $call): bool => $call->answered_at !== null);
        $waitSeconds = 0;
        $waitSamples = 0;
        foreach ($answeredTerminalCalls as $call) {
            if ($call->dialed_at === null || $call->answered_at === null) {
                continue;
            }
            $wait = (int) $call->dialed_at->diffInSeconds($call->answered_at, false);
            if ($wait < 0) {
                continue;
            }
            $waitSeconds += $wait;
            $waitSamples++;
        }
        $handleSeconds = 0;
        $handleSamples = 0;
        foreach ($terminalCalls as $call) {
            if ($call->answered_at === null) {
                continue;
            }
            $duration = $call->call_duration_seconds;
            if ($duration === null && $call->ended_at !== null) {
                $duration = (int) $call->answered_at->diffInSeconds($call->ended_at, false);
            }
            if ($duration === null || (int) $duration < 0) {
                continue;
            }
            $handleSeconds += (int) $duration;
            $handleSamples++;
        }

        return [
            'terminal' => $terminalCalls->count(),
            'answered_terminal' => $answeredTerminalCalls->count(),
            'wait_seconds' => $waitSeconds,
            'wait_samples' => $waitSamples,
            'handle_seconds' => $handleSeconds,
            'handle_samples' => $handleSamples,
        ];
    }
}
