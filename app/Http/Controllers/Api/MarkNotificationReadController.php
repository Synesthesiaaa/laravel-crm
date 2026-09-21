<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationReadService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkNotificationReadController extends Controller
{
    public function __invoke(
        Request $request,
        NotificationService $notificationService,
        NotificationReadService $readService,
    ): JsonResponse {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);
        $key = $validated['key'];
        if ($notificationService->getDetailForUser($request->user(), $key) === null
            || ! $readService->markRead($request->user(), $key)) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $summary = $notificationService->getSummaryForUser($request->user());

        return response()->json([
            'success' => true,
            'key' => $key,
            'unread' => $summary['unread'],
        ]);
    }
}
