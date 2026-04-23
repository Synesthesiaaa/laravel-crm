<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HopperLoaderService
{
    /**
     * Leads that may be placed in the predictive / next-lead hopper.
     */
    public function applyHopperEligibleFilter(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->where(function ($q) {
                $q->where('status', 'NEW')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'CALLBK')
                            ->where(function ($q3) {
                                $q3->whereNull('last_called_at')
                                    ->orWhere('last_called_at', '<', now()->subHours(1));
                            });
                    });
            });
    }

    public function isLeadHopperEligible(Lead $lead): bool
    {
        if (! $lead->enabled) {
            return false;
        }

        if ($lead->status === 'NEW') {
            return true;
        }

        if ($lead->status === 'CALLBK') {
            $last = $lead->last_called_at;

            return $last === null || $last->lt(now()->subHours(1));
        }

        return false;
    }

    /**
     * Push a single lead into the hopper if the list/campaign are active and the lead is eligible.
     */
    public function pushLeadIfEligible(Lead $lead): bool
    {
        $list = LeadList::find($lead->list_id);
        if (! $list || ! $list->active) {
            return false;
        }

        $campaign = Campaign::where('code', $lead->campaign_code)->first();
        if (! $campaign || ! $campaign->is_active) {
            return false;
        }

        if (! $this->isLeadHopperEligible($lead)) {
            return false;
        }

        if (LeadHopper::where('lead_pk', $lead->id)->whereIn('status', ['pending', 'assigned'])->exists()) {
            return false;
        }

        LeadHopper::create([
            'campaign_code' => $lead->campaign_code,
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

        Log::info('HopperLoaderService: pushed single lead to hopper', [
            'lead_id' => $lead->id,
            'campaign' => $lead->campaign_code,
        ]);

        return true;
    }

    /**
     * Remove pending hopper rows for a lead (e.g. status no longer dialable).
     */
    public function purgePendingForLeadPk(int $leadPk): int
    {
        return LeadHopper::where('lead_pk', $leadPk)
            ->where('status', 'pending')
            ->delete();
    }

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

            $query = $this->applyHopperEligibleFilter(
                Lead::query()->forList($list->id)
            );

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
            'source_id' => $lead->source_id,
            'phone_code' => $lead->phone_code,
            'title' => $lead->title,
            'first_name' => $lead->first_name,
            'middle_initial' => $lead->middle_initial,
            'last_name' => $lead->last_name,
            'alt_phone' => $lead->alt_phone,
            'email' => $lead->email,
            'address1' => $lead->address1,
            'address2' => $lead->address2,
            'address3' => $lead->address3,
            'city' => $lead->city,
            'state' => $lead->state,
            'province' => $lead->province,
            'postal_code' => $lead->postal_code,
            'country' => $lead->country,
            'gender' => $lead->gender,
            'date_of_birth' => $lead->date_of_birth?->format('Y-m-d'),
            'security_phrase' => $lead->security_phrase,
            'comments' => $lead->comments,
        ];

        return array_filter(array_merge(
            $standard,
            is_array($lead->custom_fields) ? $lead->custom_fields : [],
        ), fn ($v) => $v !== null && $v !== '');
    }
}
