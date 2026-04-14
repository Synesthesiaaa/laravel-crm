<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\CallbackService;
use App\Services\Telephony\TelephonyCampaignResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function schedule(Request $request, CallbackService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
            'callback_datetime' => ['required', 'string', 'max:32'],
            'callback_type' => ['nullable', 'string', 'in:ANYONE,USERONLY,anyone,useronly'],
            'callback_user' => ['nullable', 'string', 'max:20'],
            'callback_comments' => ['nullable', 'string', 'max:255'],
            'callback_status' => ['nullable', 'string', 'max:6'],
        ]);

        $result = $service->schedule(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
            $validated['callback_datetime'],
            strtoupper((string) ($validated['callback_type'] ?? 'ANYONE')),
            $validated['callback_user'] ?? null,
            $validated['callback_comments'] ?? null,
            (string) ($validated['callback_status'] ?? 'CALLBK'),
        );

        return $this->respond($result);
    }

    public function remove(Request $request, CallbackService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
        ]);

        return $this->respond($service->remove(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
        ));
    }

    public function info(Request $request, CallbackService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
            'search_location' => ['nullable', 'string', 'in:CURRENT,ARCHIVE,ALL,current,archive,all'],
        ]);

        return $this->respond($service->info(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
            strtoupper((string) ($validated['search_location'] ?? 'ALL')),
        ));
    }

    protected function campaign(Request $request, array $validated = []): string
    {
        $explicit = $validated['campaign'] ?? $request->input('campaign');

        return TelephonyCampaignResolver::resolve(
            $request,
            is_string($explicit) && $explicit !== '' ? $explicit : null,
        );
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
