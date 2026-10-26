<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogThrottleResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (ThrottleRequestsException $exception) {
            $this->logThrottle($request, $exception->getHeaders()['Retry-After'] ?? null);

            throw $exception;
        }

        if ($response->getStatusCode() !== 429) {
            return $response;
        }

        $this->logThrottle($request, $response->headers->get('Retry-After'));

        return $response;
    }

    private function logThrottle(Request $request, ?string $retryAfter): void
    {
        Log::channel('rate-limit')->warning('HTTP request throttled', [
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'user_id' => $request->user()?->id,
            'retry_after' => $retryAfter,
        ]);
    }
}
