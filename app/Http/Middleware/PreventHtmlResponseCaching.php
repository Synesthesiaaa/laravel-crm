<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventHtmlResponseCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        if ($response->isSuccessful() && str_contains($contentType, 'text/html')) {
            $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
