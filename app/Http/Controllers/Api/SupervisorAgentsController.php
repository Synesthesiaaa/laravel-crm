<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\CampaignDispositionRecord;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use App\Services\CampaignService;
use App\Services\Telephony\ReportingService;
use App\Support\OperationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupervisorAgentsController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected VicidialServerRepository $serverRepository,
        protected ReportingService $reportingService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaign($request, $campaigns);
        $campaignConfig = $this->campaignService->getCampaign($campaign);
        $server = $campaign !== '' ? $this->serverRepository->getForCampaign($campaign) : null;
        $remoteSnapshot = $this->fetchRemoteSnapshot($request, $campaign, $server, $today);
        $remoteAgents = $remoteSnapshot['agents'];
        $remoteAgentMetrics = $remoteSnapshot['agent_metrics'];

        $users = User::whereIn('role', ['Agent', 'Team Leader'])
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
            ->with(['attendanceLogs' => function ($q) use ($today) {
                $q->whereDate('event_time', $today)->orderByDesc('event_time');
            }])
            ->get();

        // The VICIdial campaign can differ from the CRM campaign. When the
        // mapped server exposes the Non-Agent API, use its logged-in agent list
        // to add matching CRM users from that server without filtering on the
        // VICIdial campaign code.
        if ($remoteAgents !== [] || $remoteAgentMetrics !== []) {
            $remoteUserIds = array_values(array_unique([
                ...array_keys($remoteAgents),
                ...array_keys($remoteAgentMetrics),
            ]));
            $users = User::whereIn('role', ['Agent', 'Team Leader'])
                ->whereIn('id', array_values(array_unique([
                    ...$users->pluck('id')->all(),
                    ...$remoteUserIds,
                ])))
                ->with(['attendanceLogs' => function ($q) use ($today) {
                    $q->whereDate('event_time', $today)->orderByDesc('event_time');
                }])
                ->get();
        }

        $userIds = $users->pluck('id')->all();
        $agentNames = $users->keyBy('id')->map(fn ($u) => $u->full_name ?? $u->username ?? (string) $u->id)->all();

        $activeCallRecords = CallSession::whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->active()
            ->get();
        $activeCalls = $activeCallRecords->keyBy('user_id');

        $todayCalls = CallSession::whereIn('user_id', $userIds)
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

        $todaysDispositions = CampaignDispositionRecord::whereIn('agent', array_values($agentNames))
            ->where('campaign_code', $campaign)
            ->whereDate('called_at', $today)
            ->select('agent', DB::raw('COUNT(*) as total'))
            ->groupBy('agent')
            ->pluck('total', 'agent');

        $viciSessions = VicidialAgentSession::whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->get()
            ->keyBy('user_id');

        $remoteCallStats = $remoteSnapshot['call_stats'];

        $agents = $users->map(function (User $user) use ($campaign, $activeCalls, $todayCallMetrics, $todaysDispositions, $agentNames, $viciSessions, $remoteAgents, $remoteAgentMetrics) {
            $latestLog = $user->attendanceLogs->first();
            $isOnline = $latestLog?->event_type === 'login';
            $currentCall = $activeCalls->get($user->id);
            $agentName = $agentNames[$user->id] ?? $user->username;
            $dispositions = $todaysDispositions->get($agentName, 0);
            $remote = $remoteAgents[$user->id] ?? null;
            $remoteMetrics = $remoteAgentMetrics[$user->id] ?? null;

            $status = 'offline';
            if ($remote !== null) {
                $status = $this->statusFromRemoteAgent($remote['status'], $remote['sub_status']);
            } elseif ($currentCall) {
                $status = 'oncall';
            } elseif ($isOnline) {
                $status = 'available';
            }

            $callMetrics = $todayCallMetrics->get($user->id, [
                'total' => 0,
                'terminal' => 0,
                'answered' => 0,
                'answered_terminal' => 0,
                'wait_seconds' => 0,
                'wait_samples' => 0,
                'handle_seconds' => 0,
                'handle_samples' => 0,
            ]);
            $crmAverageHandle = $callMetrics['handle_samples'] > 0
                ? round($callMetrics['handle_seconds'] / $callMetrics['handle_samples'], 1)
                : 0;
            $callsToday = $remote['calls_today'] ?? $remoteMetrics['calls_today'] ?? $callMetrics['terminal'];
            $averageHandle = $remoteMetrics['avg_handle'] ?? $crmAverageHandle;
            $averageWait = $remoteMetrics['avg_wait'] ?? (
                $callMetrics['wait_samples'] > 0
                    ? round($callMetrics['wait_seconds'] / $callMetrics['wait_samples'], 1)
                    : 0
            );

            return [
                'id' => $user->id,
                'name' => $agentName,
                'campaign_code' => $campaign,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'calls_today' => $callsToday,
                'avg_handle' => $averageHandle,
                'avg_wait' => $averageWait,
                'dispositions' => $dispositions,
                'since' => $latestLog?->event_time?->format('H:i') ?? '—',
                'current_call' => $currentCall ? [
                    'phone_number' => $currentCall->phone_number,
                    'status' => $currentCall->status,
                    'duration' => $currentCall->answered_at ? (int) now()->diffInSeconds($currentCall->answered_at) : 0,
                ] : null,
                'vici_status' => $remote['status'] ?? $viciSessions->get($user->id)?->session_status,
                'queue_count' => (int) ($remote['queue_count'] ?? $viciSessions->get($user->id)?->last_status_payload['queue_count'] ?? 0),
            ];
        });

        $crmTotalToday = (int) $todayCallMetrics->sum('terminal');
        $crmAnsweredToday = (int) $todayCallMetrics->sum('answered_terminal');
        $waitSamples = (int) $todayCallMetrics->sum('wait_samples');
        $waitSeconds = (int) $todayCallMetrics->sum('wait_seconds');
        $handleSamples = (int) $todayCallMetrics->sum('handle_samples');
        $handleSeconds = (int) $todayCallMetrics->sum('handle_seconds');
        $crmAnswerRate = $crmTotalToday > 0 ? round(($crmAnsweredToday / $crmTotalToday) * 100, 1) : 0;
        $remoteOnCallCount = $agents->where('status', 'oncall')->count();
        $crmCallsByHour = $todayCalls
            ->filter(fn (CallSession $call): bool => $call->dialed_at !== null)
            ->groupBy(fn (CallSession $call): string => $call->dialed_at->format('H'))
            ->map(fn (Collection $calls): int => $calls->count())
            ->all();
        $totalToday = $remoteCallStats['total'] ?? $crmTotalToday;
        $answeredToday = $remoteCallStats['answered'] ?? $crmAnsweredToday;
        $answerRate = $remoteCallStats['answer_rate'] ?? $crmAnswerRate;
        $callsByHour = $remoteCallStats['calls_by_hour'] ?? $crmCallsByHour;
        $remoteRealtimeStats = $remoteSnapshot['realtime_stats'];
        $remotePerformanceStats = $remoteSnapshot['performance_stats'];

        $stats = [
            'agentsOnline' => $remoteRealtimeStats['agents_online'] ?? $agents->whereIn('status', ['available', 'oncall', 'break', 'wrapup'])->count(),
            'agentsAvailable' => $remoteRealtimeStats['agents_available'] ?? $agents->where('status', 'available')->count(),
            'agentsOnCall' => $remoteRealtimeStats['agents_in_calls'] ?? $remoteOnCallCount,
            'agentsPaused' => $remoteRealtimeStats['agents_paused'] ?? $agents->where('status', 'break')->count(),
            'callsWaiting' => $remoteRealtimeStats['calls_waiting'] ?? (int) ($agents->max('queue_count') ?? 0),
            'callsActive' => $remoteRealtimeStats['agents_in_calls'] ?? max($activeCallRecords->count(), $remoteOnCallCount),
            'avgWaitTime' => $remotePerformanceStats['avg_wait'] ?? ($waitSamples > 0 ? round($waitSeconds / $waitSamples, 1) : 0),
            'avgHandleTime' => $remotePerformanceStats['avg_handle'] ?? ($handleSamples > 0 ? round($handleSeconds / $handleSamples, 1) : 0),
            'todayTotal' => $totalToday,
            'callsAnswered' => $answeredToday,
            'answerRate' => $answerRate,
            'callsByHour' => $callsByHour,
            'callSource' => $remoteCallStats !== null ? 'vicidial' : 'crm',
            'realtimeSource' => $remoteSnapshot['realtime_source'],
            'performanceSource' => $remoteSnapshot['performance_source'],
            // Keep the legacy key for API consumers while the UI uses the
            // more precise answer-rate label.
            'slaPercent' => $answerRate,
            'updatedAt' => now()->toIso8601String(),
        ];

        return response()->json([
            'agents' => $agents,
            'stats' => $stats,
            'routing' => [
                'campaign_code' => $campaign,
                'campaign_name' => $campaignConfig['name'] ?? session('campaign_name', $campaign),
                'configured' => $server !== null,
                'server_name' => $server?->server_name,
                'reporting_status' => $remoteSnapshot['reporting_status'],
                'message' => $remoteSnapshot['reporting_message'],
            ],
        ]);
    }

    /**
     * Resolve only a CRM campaign. The VICIdial campaign session is deliberately
     * not consulted because it can differ from the CRM campaign's server.
     *
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
     * Build a server-scoped snapshot using supported VICIdial Non-Agent API
     * reports. VICIdial campaign IDs are deliberately not used for routing.
     *
     * @return array{
     *     agents: array<int, array{status: string, sub_status: string, user_group: string, queue_count: int, calls_today: int|null}>,
     *     agent_metrics: array<int, array{calls_today: int, avg_handle: float|int|null, avg_wait: float|int|null}>,
     *     call_stats: array{total: int, answered: int, answer_rate: float|int, calls_by_hour: array<string, int>}|null,
     *     realtime_stats: array<string, int>|null,
     *     performance_stats: array{avg_handle?: float|int, avg_wait?: float|int}|null,
     *     realtime_source: string,
     *     performance_source: string,
     *     reporting_status: string,
     *     reporting_message: string|null
     * }
     */
    private function fetchRemoteSnapshot(Request $request, string $campaign, ?VicidialServer $server, Carbon $today): array
    {
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

        $httpOptions = [
            // Supervisor polling must fail fast when a mapped server is
            // offline; local CRM metrics should remain available.
            'connect_timeout' => 1,
            'timeout' => 3,
            'retry_times' => 0,
        ];
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
        $reportingHealth = $this->reportingHealth([
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
            'reporting_status' => $reportingHealth['status'],
            'reporting_message' => $reportingHealth['message'],
        ];
    }

    /**
     * @param  array<int, OperationResult|null>  $results
     * @return array{status: 'live'|'degraded'|'unavailable', message: string|null}
     */
    private function reportingHealth(array $results): array
    {
        $reports = array_values(array_filter($results, static fn (mixed $result): bool => $result instanceof OperationResult));
        $failedReports = array_values(array_filter($reports, static fn (OperationResult $result): bool => ! $result->success));

        if ($failedReports === []) {
            return ['status' => 'live', 'message' => null];
        }

        $message = 'Verify this CRM campaign server\'s API URL and network access, then confirm its API user has View Reports permission (levels 7/8).';
        if (count($failedReports) === count($reports)) {
            return [
                'status' => 'unavailable',
                'message' => 'VICIdial reports are unavailable. '.$message,
            ];
        }

        return [
            'status' => 'degraded',
            'message' => 'Some VICIdial reports are unavailable, so CRM fallback metrics may be incomplete. '.$message,
        ];
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
     * @return array{
     *     available: bool,
     *     agents: array<int, array{status: string, sub_status: string, user_group: string, queue_count: int, calls_today: int|null}>,
     *     user_groups: array<int, string>,
     *     stats: array<string, int>|null
     * }
     */
    private function parseLoggedAgentReport(?OperationResult $result, Collection $candidates): array
    {
        $table = $this->parseRemoteTable($result);
        $empty = ['available' => false, 'agents' => [], 'user_groups' => [], 'stats' => null];
        if ($table === null || ! $this->remoteTableHasAnyHeader($table, ['user', 'agent_user', 'username'])) {
            return $empty;
        }

        $candidatesByViciUser = $candidates->keyBy(fn (User $user): string => strtolower(trim((string) $user->vici_user)));
        $remoteAgents = [];
        $userGroups = [];
        $stats = [
            'agents_online' => 0,
            'agents_available' => 0,
            'agents_in_calls' => 0,
            'agents_paused' => 0,
        ];
        $hasQueueCount = $this->remoteTableHasAnyHeader($table, ['queue_count', 'calls_waiting', 'queue']);
        if ($hasQueueCount) {
            $stats['calls_waiting'] = 0;
        }

        foreach ($table['rows'] as $row) {
            $status = trim((string) $this->remoteRowValue($row, ['status', 'agent_status', 'state']));
            $subStatus = trim((string) $this->remoteRowValue($row, ['sub_status', 'real_time_sub_status']));
            $userGroup = trim((string) $this->remoteRowValue($row, ['user_group', 'group']));
            if ($userGroup !== '') {
                $userGroups[] = $userGroup;
            }

            $stats['agents_online']++;
            $normalizedStatus = $this->statusFromRemoteAgent($status, $subStatus);
            if ($normalizedStatus === 'available') {
                $stats['agents_available']++;
            } elseif ($normalizedStatus === 'oncall') {
                $stats['agents_in_calls']++;
            } elseif ($normalizedStatus === 'break') {
                $stats['agents_paused']++;
            }

            $queueCount = $this->nonNegativeInteger($this->remoteRowValue($row, ['queue_count', 'calls_waiting', 'queue'])) ?? 0;
            if ($hasQueueCount) {
                $stats['calls_waiting'] = max($stats['calls_waiting'], $queueCount);
            }

            $viciUser = strtolower(trim((string) $this->remoteRowValue($row, ['user', 'agent_user', 'username'])));
            $user = $candidatesByViciUser->get($viciUser);
            if (! $user) {
                continue;
            }

            $remoteAgents[$user->id] = [
                'status' => $status,
                'sub_status' => $subStatus,
                'user_group' => $userGroup,
                'queue_count' => $queueCount,
                'calls_today' => $this->nonNegativeInteger($this->remoteRowValue($row, ['calls_today', 'calls', 'total_calls'])),
            ];
        }

        return [
            'available' => true,
            'agents' => $remoteAgents,
            'user_groups' => array_values(array_unique($userGroups)),
            'stats' => $stats,
        ];
    }

    /**
     * @param  Collection<int, User>  $candidates
     * @return array{
     *     agents: array<int, array{calls_today: int, avg_handle: float|int|null, avg_wait: float|int|null}>,
     *     user_groups: array<int, string>,
     *     stats: array{avg_handle?: float|int, avg_wait?: float|int}|null
     * }
     */
    private function parseAgentPerformanceReport(?OperationResult $result, Collection $candidates): array
    {
        $table = $this->parseRemoteTable($result);
        if ($table === null || ! $this->remoteTableHasAnyHeader($table, ['user', 'agent_user', 'username'])) {
            return ['agents' => [], 'user_groups' => [], 'stats' => null];
        }

        $candidatesByViciUser = $candidates->keyBy(fn (User $user): string => strtolower(trim((string) $user->vici_user)));
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

            $remoteAgents[$user->id] = [
                'calls_today' => $calls,
                'avg_handle' => $averageTalk,
                'avg_wait' => $averageWait,
            ];
        }

        $stats = [];
        if ($talkSamples > 0) {
            $stats['avg_handle'] = round($totalTalkSeconds / $talkSamples, 1);
        }
        if ($waitSamples > 0) {
            $stats['avg_wait'] = round($totalWaitSeconds / $waitSamples, 1);
        }

        return [
            'agents' => $remoteAgents,
            'user_groups' => array_values(array_unique($userGroups)),
            'stats' => $stats !== [] ? $stats : null,
        ];
    }

    /**
     * @return array<string, int>|null
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
        ];
        $stats = [];
        $validRows = 0;

        foreach ($table['rows'] as $row) {
            $rowIsValid = false;
            foreach ($fieldMap as $target => $sourceHeaders) {
                $value = $this->nonNegativeInteger($this->remoteRowValue($row, $sourceHeaders));
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
     * @param  array<string, int>|null  $stats
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

        $headers = array_map(fn ($header): string => $this->normalizeRemoteHeader($header), $rows[0]);
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

    private function statusFromRemoteAgent(string $status, string $subStatus = ''): string
    {
        $normalized = strtolower(trim($status.' '.$subStatus));

        return match (true) {
            str_contains($normalized, 'dispo'), str_contains($normalized, 'dead') => 'wrapup',
            str_contains($normalized, 'incall'), str_contains($normalized, 'in call'), str_contains($normalized, 'active'), str_contains($normalized, 'dial'), str_contains($normalized, 'ring'), str_contains($normalized, '3-way'), str_contains($normalized, 'park') => 'oncall',
            str_contains($normalized, 'pause'), str_contains($normalized, 'break') => 'break',
            str_contains($normalized, 'ready'), str_contains($normalized, 'available'), str_contains($normalized, 'queue'), str_contains($normalized, 'wait') => 'available',
            default => 'offline',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'oncall' => 'On Call',
            'available' => 'Available',
            'break' => 'On Break',
            'wrapup' => 'Wrap-up',
            default => 'Offline',
        };
    }

    /**
     * Parse the daily aggregate returned by the mapped VICIdial server. A null
     * result means the CRM lifecycle data should be used.
     *
     * @return array{total: int, answered: int, answer_rate: float|int, calls_by_hour: array<string, int>}|null
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
     * Parse VICIdial's comma-delimited HH-count hourly breakdown.
     *
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
     * Summarize today's calls for one agent without making per-agent queries.
     * Wait time is measured from dialed_at to answered_at. Handle time uses
     * call_duration_seconds when available and falls back to answered_at to
     * ended_at for completed calls.
     *
     * @param  Collection<int, CallSession>  $calls
     * @return array{total: int, terminal: int, answered: int, answered_terminal: int, wait_seconds: int, wait_samples: int, handle_seconds: int, handle_samples: int}
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
            'total' => $calls->count(),
            'terminal' => $terminalCalls->count(),
            'answered' => $answeredCalls->count(),
            'answered_terminal' => $answeredTerminalCalls->count(),
            'wait_seconds' => $waitSeconds,
            'wait_samples' => $waitSamples,
            'handle_seconds' => $handleSeconds,
            'handle_samples' => $handleSamples,
        ];
    }
}
