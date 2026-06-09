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
        $user = $request->user();
        $readIds = $notificationService->getReadIds($user);
        $readSet = array_fill_keys($readIds, true);

        $databaseItems = $notificationService->getDatabaseForUser($user, 25)
            ->map(fn ($notification) => $notificationService->formatDatabaseNotification($notification));

        $historyRows = $notificationService->getForUser($user, 25);
        $historyItems = $historyRows->map(function ($h) use ($notificationService, $readSet) {
            $read = isset($readSet[(int) $h->id]);

            return $notificationService->formatHistoryRow($h, $read);
        });

        $items = $databaseItems->concat($historyItems)->values()->all();
        $unread = collect($items)->where('read', false)->count();

        return response()->json([
            'success' => true,
            'items' => $items,
            'unread' => $unread,
        ]);
    }
}
