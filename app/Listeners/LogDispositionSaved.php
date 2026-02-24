<?php

namespace App\Listeners;

use App\Events\DispositionSaved;
use Illuminate\Support\Facades\Log;

class LogDispositionSaved
{
    public function handle(DispositionSaved $event): void
    {
        Log::channel('audit')->info('Disposition saved', [
            'campaign'    => $event->campaignCode,
            'agent'       => $event->agent,
            'disposition' => $event->dispositionCode,
            'lead_id'     => $event->leadId,
        ]);
    }
}
