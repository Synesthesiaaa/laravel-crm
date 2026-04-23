<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\LeadHopper;
use App\Models\LeadList;
use App\Services\Leads\LeadHopperResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NextLeadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $campaign = $request->query('campaign') ?: (string) $request->session()->get('campaign', 'mbsales');
        $user = $request->user();

        // Throttle: prevent rapid-fire requests from the same agent
        $cacheKey = 'next_lead_throttle_'.$user->id;
        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'lead' => null,
                'message' => 'Please wait before fetching another lead.',
            ]);
        }
        Cache::put($cacheKey, true, now()->addSeconds(2));

        $lead = $this->fetchNextLead($campaign, $user->id);

        if ($lead) {
            return response()->json([
                'success' => true,
                'lead' => LeadHopperResponseBuilder::toApiArray($lead),
            ]);
        }

        return response()->json([
            'success' => true,
            'lead' => null,
            'message' => 'No leads available in the hopper.',
        ]);
    }

    /**
     * Atomically claim the next pending lead for this agent from the local hopper.
     * Uses a transaction + lock to prevent double-assignment in concurrent requests.
     */
    private function fetchNextLead(string $campaign, int $userId): ?LeadHopper
    {
        try {
            return DB::transaction(function () use ($campaign, $userId) {
                $campaignConfig = Campaign::where('code', $campaign)->first();
                $maxAttempts = max(1, (int) ($campaignConfig?->predictive_max_attempts ?? 3));

                $activeListIds = LeadList::forCampaign($campaign)
                    ->active()
                    ->pluck('id')
                    ->all();

                $lead = LeadHopper::forCampaign($campaign)
                    ->pending()
                    ->where('attempt_count', '<', $maxAttempts)
                    ->where(function ($q) use ($activeListIds) {
                        $q->whereNull('list_id');
                        if (! empty($activeListIds)) {
                            $q->orWhereIn('list_id', $activeListIds);
                        }
                    })
                    ->orderByDesc('priority')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $lead) {
                    return null;
                }

                $lead->update([
                    'status' => 'assigned',
                    'assigned_to_user_id' => $userId,
                    'assigned_at' => now(),
                    'last_attempted_at' => now(),
                ]);

                return $lead;
            });
        } catch (\Throwable $e) {
            Log::error('NextLeadController: failed to fetch next lead', [
                'campaign' => $campaign,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
