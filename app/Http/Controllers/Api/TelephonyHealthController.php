<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\TelephonyHealthService;
use Illuminate\Http\JsonResponse;

/**
 * Telephony health check endpoint for monitoring and load balancers.
 * GET /api/telephony/health
 */
class TelephonyHealthController extends Controller
{
    public function __construct(
        protected TelephonyHealthService $health
    ) {}

    public function __invoke(): JsonResponse
    {
        $metrics = $this->health->getMetrics();
        $status = $this->health->getStatus($metrics);

        $httpStatus = match ($status) {
            'critical' => 503,
            'degraded' => 200,
            default => 200,
        };

        return response()->json([
            'status' => $status,
            'metrics' => $metrics,
            'timestamp' => now()->toIso8601String(),
        ], $httpStatus);
    }
}
