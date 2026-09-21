<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationDetailController extends Controller
{
    public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);
        $detail = $notificationService->getDetailForUser($request->user(), $validated['key']);
        if ($detail === null) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'detail' => $detail,
        ]);
    }
}
