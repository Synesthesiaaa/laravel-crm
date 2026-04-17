<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Returns WebSocket (Reverb) config for frontend connectivity check.
 * GET /api/websocket/health
 */
class WebsocketHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $enabled = config('broadcasting.default') === 'reverb';

        return response()->json([
            'websocket_enabled' => $enabled,
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.useTLS') ? 'wss' : 'ws',
        ]);
    }
}
