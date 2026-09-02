<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VicidialSessionApiRequest;
use App\Services\Telephony\TelephonyCampaignResolver;
use App\Services\Telephony\VicidialSessionService;
use Illuminate\Http\JsonResponse;

class VicidialSessionController extends Controller
{
    public function login(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);

        $result = $service->loginAgent(
            $user,
            $campaign,
            $validated['phone_login'] ?? null,
            $validated['phone_pass'] ?? null,
            (bool) ($validated['blended'] ?? true),
            $validated['ingroups'] ?? [],
            $validated['vd_login'] ?? null,
            $validated['vd_pass'] ?? null,
        );

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
                'data' => $result->data,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'data' => $result->data,
            // iframe_url is now embedded inside data by loginAgent()
            'iframe_url' => $result->data['iframe_url'] ?? null,
            'login_state' => $result->data['login_state'] ?? 'login_pending',
        ]);
    }

    /**
     * Rebuild vicidial.php URL from the current CRM user (VD_login/VD_pass) and session phone_login
     * plus sip_password — same alignment as POST /session/login without overriding phone fields.
     */
    public function iframeUrl(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $user = $request->user();
        $session = $service->getLocalSession($user, $campaign);
        $creds = $service->resolveEffectivePhoneCredentials($user, $session->phone_login, null);
        $iframeUrl = $service->getAlignedIframeUrlForCampaign($user, $campaign);

        if ($iframeUrl === null || $iframeUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'Could not build VICIdial iframe URL. Check campaign server configuration, vici_user/vici_pass, and phone login.',
                'iframe_url' => null,
                'vd_login' => (string) ($user->vici_user ?? ''),
                'phone_login' => $creds['phone_login'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'iframe_url' => $iframeUrl,
            'vd_login' => (string) $user->vici_user,
            'phone_login' => $creds['phone_login'],
        ]);
    }

    /**
     * Called by the frontend (after iframe loads) to verify the VICIdial session
     * is actually live in vicidial_live_agents. Returns `login_state: ready` on success
     * or `login_state: login_pending` if not yet usable.
     */
    public function verify(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $validated['campaign'] ?? null,
        );
        $result = $service->verifyLogin($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'login_state' => $result->data['login_state'] ?? ($result->success ? 'ready' : 'login_pending'),
            'data' => $result->data,
        ], $result->success ? 200 : 202);
    }

    public function pause(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->pauseAgent($request->user(), $campaign, strtoupper($validated['value']));

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function pauseCode(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->setPauseCode($request->user(), $campaign, $validated['pause_code']);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function logout(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $validated['campaign'] ?? null,
        );
        $result = $service->logoutAgent($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function status(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $validated['campaign'] ?? null,
        );
        $status = $service->getAgentStatus($request->user(), $campaign);
        if ($status->success) {
            $syncedCampaign = $request->session()->get('vicidial_campaign');
            if (is_string($syncedCampaign) && $syncedCampaign !== '') {
                $campaign = $syncedCampaign;
            }
        }
        $queue = $service->getCallsInQueue($request->user(), $campaign);
        $ingroups = $service->getAgentInGroupInfo($request->user(), $campaign);
        $session = $service->getLocalSession($request->user(), $campaign);

        return response()->json([
            'success' => true,
            'session_iframe_agent_api_only' => (bool) config('vicidial.session_iframe_agent_api_only', false),
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

    public function localStatus(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $session = $service->getLocalSession($request->user(), $campaign);

        return response()->json([
            'success' => true,
            'session_iframe_agent_api_only' => (bool) config('vicidial.session_iframe_agent_api_only', false),
            'local_session' => $session,
            'agent_status' => [
                'success' => true,
                'message' => 'Local VICIdial session state.',
                'data' => [],
            ],
            'queue' => [
                'success' => true,
                'message' => 'Queue status is available on the agent screen.',
                'data' => ['count' => 0],
            ],
            'ingroup_info' => [
                'success' => true,
                'message' => 'Local session state only.',
                'data' => [],
            ],
            'pause_codes' => config('vicidial.pause_codes', []),
        ]);
    }

    public function ingroups(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->changeIngroups(
            $request->user(),
            $campaign,
            strtoupper($validated['action']),
            $validated['ingroups'] ?? [],
            (bool) ($validated['blended'] ?? true),
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
}
