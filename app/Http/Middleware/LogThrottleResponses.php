<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogThrottleResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 429) {
            return $response;
        }

        Log::channel('rate-limit')->warning('HTTP 429 throttled request', [
            'path' => $request->path(),
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'retry_after' => $response->headers->get('Retry-After'),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return $response;
    }
}
