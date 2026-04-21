<?php

namespace App\Listeners;

use App\Events\CallOriginated;
use Illuminate\Support\Facades\Log;

class LogCallOriginated
{
    public function handle(CallOriginated $event): void
    {
        Log::channel('telephony')->info('Call originated', [
            'campaign' => $event->campaignCode,
            'agent' => $event->agent,
            'phone' => $event->phoneNumber,
            'lead_id' => $event->leadId,
        ]);
    }
}
