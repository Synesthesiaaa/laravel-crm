<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Services\Telephony\AgentCampaignResolver;
use App\Services\Telephony\LeadHydrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveInboundController extends Controller
{
    public function __construct(
        protected LeadHydrationService $leadHydrationService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || empty($user->vici_user)) {
            return response()->json(['active' => false]);
        }

        try {
            $session = CallSession::where('user_id', $user->id)
                ->active()
                ->orderByDesc('dialed_at')
                ->first();
            $campaign = AgentCampaignResolver::resolveForUser($user, $session);
            $hydrated = $this->leadHydrationService->probeInbound($user, $campaign);

            if (! $hydrated) {
                return response()->json(['active' => false]);
            }

            return response()->json([
                'active' => true,
                'campaign' => $campaign,
                'lead_id' => $hydrated['lead_id'] ?? null,
                'phone_number' => $hydrated['phone_number'] ?? null,
                'client_name' => $hydrated['client_name'] ?? null,
                'capture_data' => $hydrated['capture_data'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['active' => false]);
        }
    }
}
