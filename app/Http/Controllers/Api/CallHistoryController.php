<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CallHistoryRequest;
use App\Http\Resources\HistoricalCallResource;
use App\Services\CallHistoryService;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;

class CallHistoryController extends Controller
{
    public function __construct(
        protected CallHistoryService $callHistoryService,
        protected CampaignService $campaignService,
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
}
