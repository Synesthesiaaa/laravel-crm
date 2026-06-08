<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\DtmfService;
use App\Services\Telephony\TelephonyCampaignResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DtmfController extends Controller
{
    public function __invoke(Request $request, DtmfService $service): JsonResponse
    {
        $validated = $request->validate([
            'digits' => ['required', 'string', 'max:64'],
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $service->send(
            $request->user(),
            TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null),
            $validated['digits'],
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
}
