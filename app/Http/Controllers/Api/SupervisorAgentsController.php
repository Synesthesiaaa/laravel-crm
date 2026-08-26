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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $remoteAgents = $this->fetchRemoteAgents($request, $campaign, $server);
        if ($remoteAgents !== []) {
            $remoteUserIds = array_keys($remoteAgents);
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

        $activeCalls = CallSession::whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->active()
            ->get()
            ->keyBy('user_id');

        $todaysCompleted = CallSession::whereIn('user_id', $userIds)
            ->where('campaign_code', $campaign)
            ->whereDate('dialed_at', $today)
            ->whereIn('status', ['completed', 'failed', 'abandoned'])
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

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

        $agents = $users->map(function (User $user) use ($campaign, $activeCalls, $todaysCompleted, $todaysDispositions, $agentNames, $viciSessions, $remoteAgents) {
            $latestLog = $user->attendanceLogs->first();
            $isOnline = $latestLog?->event_type === 'login';
            $currentCall = $activeCalls->get($user->id);
            $agentName = $agentNames[$user->id] ?? $user->username;
            $dispositions = $todaysDispositions->get($agentName, 0);
            $remote = $remoteAgents[$user->id] ?? null;

            $status = 'offline';
            if ($remote !== null) {
                $status = $this->statusFromRemoteAgent($remote['status']);
            } elseif ($currentCall) {
                $status = 'oncall';
            } elseif ($isOnline) {
                $status = 'available';
            }

            $callsToday = $todaysCompleted->get($user->id, 0);
            $handleTimes = $currentCall && $currentCall->answered_at
                ? [(int) now()->diffInSeconds($currentCall->answered_at)]
                : [];

            return [
                'id' => $user->id,
                'name' => $agentName,
                'campaign_code' => $campaign,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'calls_today' => $callsToday,
                'avg_handle' => 0,
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

        $stats = [
            'agentsOnline' => $agents->whereIn('status', ['available', 'oncall'])->count(),
            'callsWaiting' => 0,
            'callsActive' => $activeCalls->count(),
            'avgWaitTime' => 0,
            'todayTotal' => $todaysCompleted->sum(),
            'slaPercent' => 100,
        ];

        return response()->json([
            'agents' => $agents,
            'stats' => $stats,
            'routing' => [
                'campaign_code' => $campaign,
                'campaign_name' => $campaignConfig['name'] ?? session('campaign_name', $campaign),
                'configured' => $server !== null,
                'server_name' => $server?->server_name,
                'message' => $server === null
                    ? "No VICIdial server is configured for campaign '{$campaign}'."
                    : null,
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
     * Read logged-in agents from the server assigned to the CRM campaign.
     * The request deliberately asks for all VICIdial campaigns on that server;
     * CRM campaign-to-server mapping is the only routing boundary.
     *
     * @return array<int, array{status: string, queue_count: int}>
     */
    private function fetchRemoteAgents(Request $request, string $campaign, ?VicidialServer $server): array
    {
        if ($server === null || trim((string) $server->api_user) === '' || trim((string) $server->api_pass) === '') {
            return [];
        }

        $candidates = User::query()
            ->whereIn('role', ['Agent', 'Team Leader'])
            ->whereNotNull('vici_user')
            ->where('vici_user', '!=', '')
            ->get();
        if ($candidates->isEmpty()) {
            return [];
        }

        $result = $this->reportingService->loggedInAgents(
            $request->user(),
            $campaign,
            [
                'campaigns' => '---ALL---',
                'show_sub_status' => 'YES',
                'stage' => 'pipe',
                'header' => 'YES',
            ],
            [
                // Supervisor polling must fail fast when a mapped server is
                // offline; local CRM metrics should remain available.
                'connect_timeout' => 1,
                'timeout' => 3,
                'retry_times' => 0,
            ],
        );
        if (! $result->success) {
            return [];
        }

        $rows = array_values(array_filter((array) ($result->data['rows'] ?? []), 'is_array'));
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(fn ($header): string => $this->normalizeRemoteHeader($header), $rows[0]);
        $headerMap = array_flip($headers);
        $userIndex = $this->firstHeaderIndex($headerMap, ['user', 'agent_user', 'username']);
        if ($userIndex === null) {
            return [];
        }

        $statusIndex = $this->firstHeaderIndex($headerMap, ['status', 'agent_status', 'state']);
        $queueIndex = $this->firstHeaderIndex($headerMap, ['queue_count', 'calls_waiting', 'queue']);
        $candidatesByViciUser = $candidates->keyBy(fn (User $user): string => strtolower(trim((string) $user->vici_user)));
        $remoteAgents = [];

        foreach (array_slice($rows, 1) as $row) {
            $viciUser = strtolower(trim((string) ($row[$userIndex] ?? '')));
            $user = $candidatesByViciUser->get($viciUser);
            if (! $user) {
                continue;
            }

            $remoteAgents[$user->id] = [
                'status' => trim((string) ($statusIndex !== null ? ($row[$statusIndex] ?? '') : '')),
                'queue_count' => $queueIndex !== null && is_numeric($row[$queueIndex] ?? null)
                    ? (int) $row[$queueIndex]
                    : 0,
            ];
        }

        return $remoteAgents;
    }

    private function normalizeRemoteHeader(mixed $header): string
    {
        $normalized = strtolower(trim((string) $header));

        return preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: '';
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  array<int, string>  $keys
     */
    private function firstHeaderIndex(array $headerMap, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($headerMap[$key])) {
                return $headerMap[$key];
            }
        }

        return null;
    }

    private function statusFromRemoteAgent(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match (true) {
            str_contains($normalized, 'incall'), str_contains($normalized, 'in call'), str_contains($normalized, 'active') => 'oncall',
            str_contains($normalized, 'pause'), str_contains($normalized, 'break') => 'break',
            str_contains($normalized, 'ready'), str_contains($normalized, 'available'), str_contains($normalized, 'queue') => 'available',
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
}
