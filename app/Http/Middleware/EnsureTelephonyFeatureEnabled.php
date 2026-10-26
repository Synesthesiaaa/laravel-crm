<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TelephonyFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTelephonyFeatureEnabled
{
    public function __construct(private readonly TelephonyFeatureService $featureService) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($request->user()?->role === User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        if (! $this->featureService->isEnabled($feature)) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                abort(403, 'This telephony feature is disabled by administrator.');
            }

            return response()->json([
                'ok' => false,
                'message' => 'This telephony feature is disabled by administrator.',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
