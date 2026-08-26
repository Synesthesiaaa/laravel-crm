<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\CampaignDispositionRecord;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Repositories\VicidialServerRepository;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupervisorAgentsController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected VicidialServerRepository $serverRepository,
    ) {}

    public function __invoke(): JsonResponse
    {
        $today = Carbon::today();
        $campaign = trim((string) session('campaign', ''));
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

        $agents = $users->map(function (User $user) use ($campaign, $activeCalls, $todaysCompleted, $todaysDispositions, $agentNames, $viciSessions) {
            $latestLog = $user->attendanceLogs->first();
            $isOnline = $latestLog?->event_type === 'login';
            $currentCall = $activeCalls->get($user->id);
            $agentName = $agentNames[$user->id] ?? $user->username;
            $dispositions = $todaysDispositions->get($agentName, 0);

            $status = 'offline';
            if ($currentCall) {
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
                'vici_status' => $viciSessions->get($user->id)?->session_status,
                'queue_count' => (int) ($viciSessions->get($user->id)?->last_status_payload['queue_count'] ?? 0),
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'oncall' => 'On Call',
            'available' => 'Available',
            default => 'Offline',
        };
    }
}
