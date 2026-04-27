<?php

namespace App\Observers;

use App\Jobs\PushLeadToHopperJob;
use App\Models\Lead;
use App\Services\Leads\HopperLoaderService;

class LeadObserver
{
    protected static bool $suppressCreatedHopperDispatch = false;

    public function __construct(
        protected HopperLoaderService $hopperLoader,
    ) {}

    public static function suppressCreatedHopperDispatch(bool $suppress = true): void
    {
        self::$suppressCreatedHopperDispatch = $suppress;
    }

    public function created(Lead $lead): void
    {
        if (self::$suppressCreatedHopperDispatch) {
            return;
        }

        if ($lead->status === 'NEW' && $lead->enabled) {
            PushLeadToHopperJob::dispatch($lead->id)->afterCommit();
        }
    }

    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged('status') && ! $this->hopperLoader->isLeadHopperEligible($lead)) {
            $this->hopperLoader->purgePendingForLeadPk($lead->id);
        }
    }
}
