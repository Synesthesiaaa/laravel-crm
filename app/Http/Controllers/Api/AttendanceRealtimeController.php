<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceRealtimeService;
use Illuminate\Http\JsonResponse;

class AttendanceRealtimeController extends Controller
{
    public function __construct(
        protected AttendanceRealtimeService $attendanceRealtimeService,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->attendanceRealtimeService->snapshot(),
        ]);
    }
}
