<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClientErrorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $event = $request->validate([
            'error_type' => ['required', 'string', Rule::in(['javascript_error', 'promise_rejection', 'resource_error'])],
            'line' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'column' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        Log::warning('Client-side error reported', [
            'route' => $request->route()?->getName() ?? 'unknown',
            'error_type' => $event['error_type'],
            'line' => $event['line'] ?? null,
            'column' => $event['column'] ?? null,
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return response()->json(['ok' => true]);
    }
}
