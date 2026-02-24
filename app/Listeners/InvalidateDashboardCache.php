<?php

namespace App\Listeners;

use App\Events\FormSubmitted;
use Illuminate\Support\Facades\Cache;

class InvalidateDashboardCache
{
    public function handle(FormSubmitted $event): void
    {
        Cache::forget("admin_form_stats_{$event->campaignCode}");
    }
}
