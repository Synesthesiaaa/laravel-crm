<?php

namespace App\Listeners;

use App\Events\LeadImported;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifyLeadImportComplete
{
    public function handle(LeadImported $event): void
    {
        Cache::forget("admin_form_stats_{$event->campaignCode}");

        Log::channel('audit')->info('Lead import completed', [
            'campaign'  => $event->campaignCode,
            'count'     => $event->importedCount,
            'uploaded_by' => $event->uploadedByUserId,
        ]);
    }
}
