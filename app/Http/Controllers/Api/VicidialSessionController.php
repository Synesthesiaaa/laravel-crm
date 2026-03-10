<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\VicidialSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VicidialSessionController extends Controller
{
    public function login(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'phone_login' => ['nullable', 'string', 'max:32'],
            'phone_pass' => ['nullable', 'string', 'max:32'],
            'blended' => ['nullable', 'boolean'],
            'ingroups' => ['nullable', 'array'],
            'ingroups.*' => ['string', 'max:32'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result = $service->loginAgent(
            $request->user(),
            $campaign,
            $validated['phone_login'] ?? null,
            $validated['phone_pass'] ?? null,
            (bool) ($validated['blended'] ?? true),
            $validated['ingroups'] ?? []
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function pause(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'value' => ['required', 'string', 'in:PAUSE,RESUME,pause,resume'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result = $service->pauseAgent($request->user(), $campaign, strtoupper($validated['value']));

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function pauseCode(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'pause_code' => ['required', 'string', 'max:16'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result = $service->setPauseCode($request->user(), $campaign, $validated['pause_code']);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function logout(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = (string) $request->input('campaign', $request->session()->get('campaign', 'mbsales'));
        $result = $service->logoutAgent($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function status(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = (string) $request->input('campaign', $request->session()->get('campaign', 'mbsales'));
        $status = $service->getAgentStatus($request->user(), $campaign);
        $queue = $service->getCallsInQueue($request->user(), $campaign);
        $ingroups = $service->getAgentInGroupInfo($request->user(), $campaign);
        $session = $service->getLocalSession($request->user(), $campaign);

        return response()->json([
            'success' => true,
            'local_session' => $session,
            'agent_status' => [
                'success' => $status->success,
                'message' => $status->message,
                'data' => $status->data,
            ],
            'queue' => [
                'success' => $queue->success,
                'message' => $queue->message,
                'data' => $queue->data,
            ],
            'ingroup_info' => [
                'success' => $ingroups->success,
                'message' => $ingroups->message,
                'data' => $ingroups->data,
            ],
            'pause_codes' => config('vicidial.pause_codes', []),
        ]);
    }

    public function ingroups(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'action' => ['required', 'string', 'in:CHANGE,ADD,REMOVE,change,add,remove'],
            'ingroups' => ['nullable', 'array'],
            'ingroups.*' => ['string', 'max:32'],
            'blended' => ['nullable', 'boolean'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result = $service->changeIngroups(
            $request->user(),
            $campaign,
            strtoupper($validated['action']),
            $validated['ingroups'] ?? [],
            (bool) ($validated['blended'] ?? true)
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
}
