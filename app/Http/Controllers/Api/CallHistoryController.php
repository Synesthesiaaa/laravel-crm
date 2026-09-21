<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CallHistoryRequest;
use App\Http\Resources\HistoricalCallResource;
use App\Jobs\SyncVicidialCallHistoryJob;
use App\Models\VicidialCallHistorySyncState;
use App\Services\CallHistoryService;
use App\Services\CampaignService;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\LocalCallHistoryQueryService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CallHistoryController extends Controller
{
    public function __construct(
        protected CallHistoryService $callHistoryService,
        protected CampaignService $campaignService,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
        protected LocalCallHistoryQueryService $localCallHistory,
    ) {}

    public function index(CallHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = $this->campaignService->resolveCampaignForRequest(
            $request,
            $validated['campaign'] ?? null,
        )['code'];
        $page = $this->callHistoryService->getHistoricalHistory(
            $request->user(),
            $campaign,
            $validated,
            ! $request->user()->isTeamLeader(),
            (int) ($validated['per_page'] ?? 25),
        );
        $data = collect($page->records->getCollection())
            ->map(fn (mixed $record): array => (new HistoricalCallResource($record))->toArray($request))
            ->values()
            ->all();

        return response()->json([
            'success' => $page->available,
            'state' => $page->state,
            'message' => $page->message,
            'data' => $data,
            'pagination' => [
                'current_page' => $page->records->currentPage(),
                'last_page' => $page->records->lastPage(),
                'per_page' => $page->records->perPage(),
                'total' => $page->records->total(),
                'from' => $page->records->firstItem(),
                'to' => $page->records->lastItem(),
                'has_more_pages' => $page->records->hasMorePages(),
            ],
            'scope' => $page->scope,
            'filters' => [
                'applied' => $validated,
                'available' => $page->filterOptions,
            ],
            'source_health' => $page->sourceHealth,
        ], $page->available ? 200 : 503);
    }

    public function refresh(CallHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = $this->campaignService->resolveCampaignForRequest(
            $request,
            $validated['campaign'] ?? null,
        )['code'];
        $scope = $this->scopeResolver->resolve($campaign);

        if (! $scope->campaign->exists || $scope->server === null || $scope->historicalCampaignCodes() === []) {
            return response()->json([
                'success' => false,
                'state' => 'unavailable',
                'message' => 'Call History synchronization is not configured for this campaign.',
            ], 503);
        }

        $state = VicidialCallHistorySyncState::query()->firstOrCreate([
            'vicidial_server_id' => $scope->server->getKey(),
            'crm_campaign_id' => $scope->campaign->getKey(),
        ], [
            'status' => VicidialCallHistorySyncState::STATUS_NEVER_SYNCED,
        ]);
        $alreadyRunning = $state->status === VicidialCallHistorySyncState::STATUS_RUNNING
            && $state->last_started_at?->greaterThan(now()->subMinutes(2));
        if (! $alreadyRunning) {
            $state->update([
                'status' => VicidialCallHistorySyncState::STATUS_RUNNING,
                'last_started_at' => now(),
            ]);

            try {
                SyncVicidialCallHistoryJob::dispatch(
                    (int) $scope->campaign->getKey(),
                    serverId: (int) $scope->server->getKey(),
                );
            } catch (Throwable) {
                $state->update([
                    'status' => VicidialCallHistorySyncState::STATUS_FAILED,
                    'last_failed_at' => now(),
                    'last_error_classification' => 'QUEUE_DISPATCH_ERROR',
                    'last_error_message' => 'Call History refresh could not be queued. Please try again.',
                ]);

                return response()->json([
                    'success' => false,
                    'state' => 'unavailable',
                    'message' => 'Call History refresh could not be queued. Please try again.',
                    'source_health' => $this->localCallHistory->syncHealth($campaign),
                ], 503);
            }
        }

        return response()->json([
            'success' => true,
            'state' => 'queued',
            'message' => $alreadyRunning ? 'A Call History refresh is already in progress.' : 'Call History refresh queued.',
            'duplicate_suppressed' => $alreadyRunning,
            'source_health' => $this->localCallHistory->syncHealth($campaign),
        ], 202);
    }

    public function status(CallHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = $this->campaignService->resolveCampaignForRequest(
            $request,
            $validated['campaign'] ?? null,
        )['code'];

        return response()->json([
            'success' => true,
            'state' => 'ready',
            'source_health' => $this->localCallHistory->syncHealth($campaign),
        ]);
    }
}
