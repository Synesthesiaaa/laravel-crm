<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationReadService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkNotificationsReadController extends Controller
{
    public function __invoke(
        Request $request,
        NotificationService $notificationService,
        NotificationReadService $readService,
    ): JsonResponse {
        $user = $request->user();
        $keys = $notificationService->visibleKeys($user);
        $readService->markMany($user, $keys);
        $historyIds = collect($keys)
            ->filter(static fn (string $key): bool => str_starts_with($key, 'history:'))
            ->map(static fn (string $key): int => (int) substr($key, strlen('history:')))
            ->filter()
            ->all();
        $notificationService->markIdsRead($user, $historyIds);

        return response()->json(['ok' => true, 'unread' => 0]);
    }
}
