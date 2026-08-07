<?php

namespace App\Http\Middleware;

use App\Services\UserActivityRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditUserActivity
{
    public function __construct(
        private UserActivityRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $response = $next($request);

        $this->recorder->record($request, $response, $user ?? $request->user());

        return $response;
    }
}
