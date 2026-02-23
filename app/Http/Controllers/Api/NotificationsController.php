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
        $items = $notificationService->getForUser($request->user(), 25);
        $data = $items->map(fn ($h) => [
            'id' => $h->id,
            'form_type' => $h->form_type,
            'created_at' => $h->created_at?->toIso8601String(),
        ])->all();
        return response()->json(['success' => true, 'items' => $data]);
    }
}
