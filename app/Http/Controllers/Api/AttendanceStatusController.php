<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStatusType;
use App\Services\AttendanceStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceStatusController extends Controller
{
    public function __construct(
        protected AttendanceStatusService $attendanceStatusService,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
        ]);

        try {
            $log = $this->attendanceStatusService->startStatus(
                $request->user(),
                $validated['code'],
                $request->ip(),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $log->load('statusType');

        return response()->json([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'direction' => $log->direction,
                'event_time' => $log->event_time?->toIso8601String(),
                'status_label' => $log->statusType?->label,
            ],
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        try {
            $log = $this->attendanceStatusService->endStatus($request->user(), $request->ip());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $log->load('statusType');

        return response()->json([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'direction' => $log->direction,
                'event_time' => $log->event_time?->toIso8601String(),
                'status_label' => $log->statusType?->label,
            ],
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $open = $this->attendanceStatusService->getOpenStatus($request->user());
        if ($open !== null) {
            $open->load('statusType');
        }

        $types = AttendanceStatusType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'label', 'sort_order']);

        return response()->json([
            'success' => true,
            'open' => $open ? [
                'id' => $open->id,
                'code' => $open->statusType?->code,
                'label' => $open->statusType?->label,
                'started_at' => $open->event_time?->toIso8601String(),
                'attendance_status_type_id' => $open->attendance_status_type_id,
            ] : null,
            'types' => $types,
        ]);
    }
}
