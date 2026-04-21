<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HopperLoaderService
{
    /**
     * Push eligible leads from a single list into the hopper.
     * Only runs if the list is enabled and its campaign is active.
     *
     * Returns number of leads pushed.
     */
    public function loadList(LeadList $list, int $limit = 500): int
    {
        if (! $list->active) {
            return 0;
        }

        $campaign = Campaign::where('code', $list->campaign_code)->first();
        if (! $campaign || ! $campaign->is_active) {
            return 0;
        }

        return DB::transaction(function () use ($list, $limit) {
            $existingPks = LeadHopper::where('list_id', $list->id)
                ->whereIn('status', ['pending', 'assigned'])
                ->pluck('lead_pk')
                ->filter()
                ->all();

            $query = Lead::query()
                ->forList($list->id)
                ->dialable();

            if (! empty($existingPks)) {
                $query->whereNotIn('id', $existingPks);
            }

            $leads = $query->orderBy('id')->limit($limit)->get();
            $inserted = 0;

            foreach ($leads as $lead) {
                LeadHopper::create([
                    'campaign_code' => $list->campaign_code,
                    'list_id' => $list->id,
                    'lead_pk' => $lead->id,
                    'lead_id' => (string) $lead->id,
                    'phone_number' => $lead->phone_number,
                    'client_name' => $lead->displayName(),
                    'custom_data' => $this->buildCustomData($lead),
                    'priority' => 0,
                    'status' => 'pending',
                    'attempt_count' => 0,
                ]);
                $inserted++;
            }

            Log::info('HopperLoaderService: loaded leads from list', [
                'list_id' => $list->id,
                'campaign' => $list->campaign_code,
                'count' => $inserted,
            ]);

            return $inserted;
        });
    }

    /**
     * Load all enabled lists for a campaign.
     */
    public function loadCampaign(string $campaignCode, int $perList = 500): int
    {
        $total = 0;
        $lists = LeadList::forCampaign($campaignCode)->active()->ordered()->get();
        foreach ($lists as $list) {
            $total += $this->loadList($list, $perList);
        }

        return $total;
    }

    /**
     * Purge pending hopper rows that belong to a disabled list.
     * Rows that are already assigned / in-progress are left alone.
     */
    public function purgePendingForList(int $listId): int
    {
        return LeadHopper::where('list_id', $listId)
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomData(Lead $lead): array
    {
        $standard = [
            'vendor_lead_code' => $lead->vendor_lead_code,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'city' => $lead->city,
            'state' => $lead->state,
            'postal_code' => $lead->postal_code,
        ];

        return array_filter(array_merge(
            $standard,
            is_array($lead->custom_fields) ? $lead->custom_fields : [],
        ), fn ($v) => $v !== null && $v !== '');
    }
}
