<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\RecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function start(Request $request, RecordingService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:14'],
        ]);

        $result = $service->startRecording(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['stage'] ?? null
        );

        return $this->respond($result);
    }

    public function stop(Request $request, RecordingService $service): JsonResponse
    {
        return $this->respond($service->stopRecording($request->user(), $this->campaign($request)));
    }

    public function status(Request $request, RecordingService $service): JsonResponse
    {
        return $this->respond($service->getRecordingStatus($request->user(), $this->campaign($request)));
    }

    public function lookup(Request $request, RecordingService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'agent_user' => ['nullable', 'string', 'max:20'],
            'lead_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'extension' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->respond($service->lookupRecordings($request->user(), $this->campaign($request, $validated), $validated));
    }

    protected function campaign(Request $request, array $validated = []): string
    {
        return (string) ($validated['campaign'] ?? $request->input('campaign', $request->session()->get('campaign', 'mbsales')));
    }

    protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
}
