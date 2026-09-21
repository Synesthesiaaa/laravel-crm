<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        $feed = $notificationService->getFeedForUser($request->user());

        return response()->json([
            'success' => true,
            ...$feed,
        ]);
    }
}
