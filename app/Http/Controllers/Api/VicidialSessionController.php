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
            'campaign'    => ['nullable', 'string', 'max:50'],
            'phone_login' => ['nullable', 'string', 'max:32'],
            'phone_pass'  => ['nullable', 'string', 'max:32'],
            'blended'     => ['nullable', 'boolean'],
            'ingroups'    => ['nullable', 'array'],
            'ingroups.*'  => ['string', 'max:32'],
        ]);

        $user     = $request->user();
        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');

        $result = $service->loginAgent(
            $user,
            $campaign,
            $validated['phone_login'] ?? null,
            $validated['phone_pass']  ?? null,
            (bool) ($validated['blended'] ?? true),
            $validated['ingroups'] ?? []
        );

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
                'data'    => $result->data,
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'message'    => $result->message,
            'data'       => $result->data,
            // iframe_url is now embedded inside data by loginAgent()
            'iframe_url' => $result->data['iframe_url'] ?? null,
            'login_state' => $result->data['login_state'] ?? 'login_pending',
        ]);
    }

    /**
     * Called by the frontend (after iframe loads) to verify the VICIdial session
     * is actually live in vicidial_live_agents. Returns `login_state: ready` on success
     * or `login_state: login_pending` if not yet usable.
     */
    public function verify(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = (string) $request->input('campaign', $request->session()->get('campaign', 'mbsales'));
        $result   = $service->verifyLogin($request->user(), $campaign);

        return response()->json([
            'success'    => $result->success,
            'message'    => $result->message,
            'login_state' => $result->data['login_state'] ?? ($result->success ? 'ready' : 'login_pending'),
            'data'       => $result->data,
        ], $result->success ? 200 : 202);
    }

    public function pause(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'value'    => ['required', 'string', 'in:PAUSE,RESUME,pause,resume'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result   = $service->pauseAgent($request->user(), $campaign, strtoupper($validated['value']));

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function pauseCode(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign'   => ['nullable', 'string', 'max:50'],
            'pause_code' => ['required', 'string', 'max:6'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result   = $service->setPauseCode($request->user(), $campaign, $validated['pause_code']);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function logout(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = (string) $request->input('campaign', $request->session()->get('campaign', 'mbsales'));
        $result   = $service->logoutAgent($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function status(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = (string) $request->input('campaign', $request->session()->get('campaign', 'mbsales'));
        $status   = $service->getAgentStatus($request->user(), $campaign);
        $queue    = $service->getCallsInQueue($request->user(), $campaign);
        $ingroups = $service->getAgentInGroupInfo($request->user(), $campaign);
        $session  = $service->getLocalSession($request->user(), $campaign);

        return response()->json([
            'success'       => true,
            'local_session' => $session,
            'agent_status'  => [
                'success' => $status->success,
                'message' => $status->message,
                'data'    => $status->data,
            ],
            'queue' => [
                'success' => $queue->success,
                'message' => $queue->message,
                'data'    => $queue->data,
            ],
            'ingroup_info' => [
                'success' => $ingroups->success,
                'message' => $ingroups->message,
                'data'    => $ingroups->data,
            ],
            'pause_codes' => config('vicidial.pause_codes', []),
        ]);
    }

    public function ingroups(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign'   => ['nullable', 'string', 'max:50'],
            'action'     => ['required', 'string', 'in:CHANGE,ADD,REMOVE,change,add,remove'],
            'ingroups'   => ['nullable', 'array'],
            'ingroups.*' => ['string', 'max:32'],
            'blended'    => ['nullable', 'boolean'],
        ]);

        $campaign = $validated['campaign'] ?? $request->session()->get('campaign', 'mbsales');
        $result   = $service->changeIngroups(
            $request->user(),
            $campaign,
            strtoupper($validated['action']),
            $validated['ingroups'] ?? [],
            (bool) ($validated['blended'] ?? true)
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ], $result->success ? 200 : 422);
    }
}
