<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelephonyAlert;
use App\Services\Telephony\TelephonyHealthService;
use Inertia\Response;

/**
 * Telephony monitoring dashboard for admins.
 */
class TelephonyMonitorController extends Controller
{
    public function __construct(
        protected TelephonyHealthService $health
    ) {}

    public function index(): Response
    {
        $metrics = $this->health->getMetrics();
        $status = $this->health->getStatus($metrics);
        $recentAlerts = TelephonyAlert::recent(24)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $this->inertiaAdmin('admin.inline-telephony-monitor', [
            'status' => $status,
            'metrics' => $metrics,
            'recentAlerts' => $recentAlerts,
        ], 'Telephony Monitor');
    }
}
