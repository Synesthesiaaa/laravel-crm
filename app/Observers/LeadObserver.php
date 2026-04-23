<?php

namespace App\Observers;

use App\Jobs\PushLeadToHopperJob;
use App\Models\Lead;
use App\Services\Leads\HopperLoaderService;

class LeadObserver
{
    public function __construct(
        protected HopperLoaderService $hopperLoader,
    ) {}

    public function created(Lead $lead): void
    {
        if ($lead->status === 'NEW' && $lead->enabled) {
            PushLeadToHopperJob::dispatch($lead->id);
        }
    }

    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged('status') && ! $this->hopperLoader->isLeadHopperEligible($lead)) {
            $this->hopperLoader->purgePendingForLeadPk($lead->id);
        }
    }
}
