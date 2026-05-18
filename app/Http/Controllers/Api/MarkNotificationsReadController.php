<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkNotificationsReadController extends Controller
{
    public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        $user = $request->user();
        $ids = $notificationService->getForUser($user, 25)->pluck('id')->all();
        $notificationService->markIdsRead($user, $ids);

        return response()->json(['ok' => true]);
    }
}
