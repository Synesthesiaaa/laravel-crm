<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\LeadHopper;
use App\Models\User;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;

class PredictiveDialerService
{
    public function __construct(
        protected CallOrchestrationService $orchestration,
        protected TelephonyLogger $telephonyLogger
    ) {}

    /**
     * Fetch next eligible lead from hopper and immediately originate a call.
     */
    public function dialNext(User $user, string $campaignCode): OperationResult
    {
        $campaign = Campaign::where('code', $campaignCode)->first();
        if (! $campaign || ! $campaign->predictive_enabled) {
            return OperationResult::failure('Predictive dialing is disabled for this campaign.');
        }

        $lead = DB::transaction(function () use ($campaignCode, $campaign, $user) {
            $maxAttempts = max(1, (int) ($campaign->predictive_max_attempts ?? 3));

            $lead = LeadHopper::forCampaign($campaignCode)
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
                'status' => 'assigned',
                'assigned_to_user_id' => $user->id,
                'assigned_at' => now(),
                'last_attempted_at' => now(),
            ]);

            return $lead->fresh();
        });

        if (! $lead) {
            return OperationResult::success([
                'lead' => null,
                'message' => 'No leads available in the hopper.',
            ]);
        }

        $result = $this->orchestration->startOutboundCall(
            $user,
            $campaignCode,
            (string) $lead->phone_number,
            is_numeric($lead->lead_id) ? (int) $lead->lead_id : null
        );

        if (! $result->success) {
            $lead->update([
                'status' => 'pending',
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'attempt_count' => (int) $lead->attempt_count + 1,
            ]);

            $this->telephonyLogger->warning('PredictiveDialerService', 'Predictive dial failed; lead returned to hopper', [
                'campaign' => $campaignCode,
                'lead_id' => $lead->lead_id,
                'phone_number' => $lead->phone_number,
                'error' => $result->message,
            ]);

            return $result;
        }

        return OperationResult::success([
            'lead' => [
                'lead_id' => $lead->lead_id,
                'phone_number' => $lead->phone_number,
                'client_name' => $lead->client_name,
                'custom_data' => $lead->custom_data ?? [],
            ],
            'session_id' => $result->data['session_id'] ?? null,
            'predictive_delay_seconds' => (int) ($campaign->predictive_delay_seconds ?? 3),
        ]);
    }
}
