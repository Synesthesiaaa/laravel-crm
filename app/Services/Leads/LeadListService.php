<?php

namespace App\Services\Leads;

use App\Models\LeadList;
use App\Support\OperationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadListService
{
    public function __construct(
        protected HopperLoaderService $hopperLoader,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LeadList
    {
        return LeadList::create([
            'campaign_code' => $data['campaign_code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'reset_time' => $data['reset_time'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LeadList $list, array $data): LeadList
    {
        $list->update([
            'name' => $data['name'] ?? $list->name,
            'description' => $data['description'] ?? $list->description,
            'reset_time' => $data['reset_time'] ?? $list->reset_time,
            'display_order' => (int) ($data['display_order'] ?? $list->display_order),
        ]);

        return $list->fresh();
    }

    /**
     * Toggle active flag. On disable, purge pending hopper rows so agents
     * don't receive leads from a deactivated list.
     */
    public function toggleActive(LeadList $list, bool $active): OperationResult
    {
        return DB::transaction(function () use ($list, $active) {
            $list->update(['active' => $active]);

            $purged = 0;
            if (! $active) {
                $purged = $this->hopperLoader->purgePendingForList($list->id);
                Log::info('LeadListService: list disabled, hopper purged', [
                    'list_id' => $list->id,
                    'purged' => $purged,
                ]);
            }

            return OperationResult::success([
                'list' => $list->fresh(),
                'purged' => $purged,
            ], $active ? 'List enabled.' : 'List disabled; pending hopper rows purged.');
        });
    }

    public function delete(LeadList $list): OperationResult
    {
        return DB::transaction(function () use ($list) {
            $this->hopperLoader->purgePendingForList($list->id);
            $list->delete();

            return OperationResult::success(null, 'List deleted.');
        });
    }

    public function refreshLeadsCount(LeadList $list): void
    {
        $list->update([
            'leads_count' => $list->leads()->count(),
        ]);
    }
}
