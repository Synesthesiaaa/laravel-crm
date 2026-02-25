<?php

namespace App\Listeners;

use App\Events\CallStateChanged;
use Illuminate\Support\Facades\Log;

class LogCallStateChanged
{
    public function handle(CallStateChanged $event): void
    {
        Log::channel('telephony')->info('Call state changed', [
            'session_id' => $event->session->id,
            'user_id' => $event->session->user_id,
            'from' => $event->fromStatus,
            'to' => $event->toStatus,
            'phone' => $event->session->phone_number,
            'campaign' => $event->session->campaign_code,
        ]);
    }
}
