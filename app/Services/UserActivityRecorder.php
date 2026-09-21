<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UserActivityRecorder
{
    public function __construct(
        private ActivityLogSanitizer $sanitizer,
    ) {}

    public function record(Request $request, Response $response, ?Authenticatable $user): void
    {
        if ($user === null) {
            return;
        }

        try {
            $route = $request->route();
            $query = $this->sanitizer->sanitize($request->query());

            activity('request')
                ->causedBy($user)
                ->event('request')
                ->withProperties([
                    'request' => [
                        'method' => $request->method(),
                        'path' => '/'.$request->path(),
                        'route' => $route?->getName(),
                        'status' => $response->getStatusCode(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'query' => $query,
                    ],
                ])
                ->log($request->method().' /'.$request->path());
        } catch (\Throwable $exception) {
            Log::channel('audit')->warning('User activity request recording failed.', [
                'user_id' => $user->getAuthIdentifier(),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'exception' => $exception::class,
            ]);
        }
    }
}
