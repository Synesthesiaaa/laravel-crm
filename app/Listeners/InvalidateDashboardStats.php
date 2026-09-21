<?php

namespace App\Listeners;

use App\Events\DashboardDataUpdated;
use App\Services\DashboardStatsService;

class InvalidateDashboardStats
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStats,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(DashboardDataUpdated $event): void
    {
        $this->dashboardStats->invalidate($event->campaignCode);
    }
}
