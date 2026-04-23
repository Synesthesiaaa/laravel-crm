<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Leads\HopperLoaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushLeadToHopperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $leadId) {}

    public function handle(HopperLoaderService $hopperLoader): void
    {
        $lead = Lead::find($this->leadId);
        if (! $lead) {
            return;
        }

        $hopperLoader->pushLeadIfEligible($lead);
    }
}
