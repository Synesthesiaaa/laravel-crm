<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSummaryController extends Controller
{
    public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$notificationService->getSummaryForUser($request->user()),
        ]);
    }
}
