<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\LeadHopper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NextLeadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');
        $user = $request->user();

        // Throttle: prevent rapid-fire requests from the same agent
        $cacheKey = 'next_lead_throttle_' . $user->id;
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
                'lead' => [
                    'lead_id'      => $lead->lead_id,
                    'phone_number' => $lead->phone_number,
                    'client_name'  => $lead->client_name,
                    'custom_data'  => $lead->custom_data ?? [],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'lead'    => null,
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

                $lead = LeadHopper::forCampaign($campaign)
                    ->pending()
                    ->where('attempt_count', '<', $maxAttempts)
                    ->orderByDesc('priority')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $lead) {
                    return null;
                }

                $lead->update([
                    'status'              => 'assigned',
                    'assigned_to_user_id' => $userId,
                    'assigned_at'         => now(),
                    'last_attempted_at'   => now(),
                ]);

                return $lead;
            });
        } catch (\Throwable $e) {
            Log::error('NextLeadController: failed to fetch next lead', [
                'campaign' => $campaign,
                'user_id'  => $userId,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }
}
