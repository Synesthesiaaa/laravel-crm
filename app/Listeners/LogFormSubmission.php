<?php

namespace App\Listeners;

use App\Events\FormSubmitted;
use Illuminate\Support\Facades\Log;

class LogFormSubmission
{
    public function handle(FormSubmitted $event): void
    {
        Log::channel('audit')->info('Form submitted', [
            'campaign'  => $event->campaignCode,
            'form_type' => $event->formType,
            'record_id' => $event->recordId,
            'agent'     => $event->agent,
        ]);
    }
}
