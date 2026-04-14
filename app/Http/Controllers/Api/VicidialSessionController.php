<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\TelephonyCampaignResolver;
use App\Services\Telephony\VicidialAgentCampaignsService;
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
            'vd_login' => ['nullable', 'string', 'max:32'],
            'vd_pass' => ['nullable', 'string', 'max:32'],
            'blended' => ['nullable', 'boolean'],
            'ingroups' => ['nullable', 'array'],
            'ingroups.*' => ['string', 'max:32'],
        ]);

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
    public function iframeUrl(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

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
    public function verify(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $request->input('campaign') !== null && $request->input('campaign') !== ''
                ? (string) $request->input('campaign')
                : null,
        );
        $result = $service->verifyLogin($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'login_state' => $result->data['login_state'] ?? ($result->success ? 'ready' : 'login_pending'),
            'data' => $result->data,
        ], $result->success ? 200 : 202);
    }

    public function pause(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'value' => ['required', 'string', 'in:PAUSE,RESUME,pause,resume'],
        ]);

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
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
            'pause_code' => ['required', 'string', 'max:6'],
        ]);

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->setPauseCode($request->user(), $campaign, $validated['pause_code']);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function logout(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $request->input('campaign') !== null && $request->input('campaign') !== ''
                ? (string) $request->input('campaign')
                : null,
        );
        $result = $service->logoutAgent($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }

    public function status(Request $request, VicidialSessionService $service): JsonResponse
    {
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $request->input('campaign') !== null && $request->input('campaign') !== ''
                ? (string) $request->input('campaign')
                : null,
        );
        $status = $service->getAgentStatus($request->user(), $campaign);
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

    public function ingroups(Request $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'action' => ['required', 'string', 'in:CHANGE,ADD,REMOVE,change,add,remove'],
            'ingroups' => ['nullable', 'array'],
            'ingroups.*' => ['string', 'max:32'],
            'blended' => ['nullable', 'boolean'],
        ]);

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

    /**
     * Campaigns the VICIdial agent user is allowed to log into (from Non-Agent API or DB).
     */
    public function agentCampaigns(Request $request, VicidialAgentCampaignsService $campaigns): JsonResponse
    {
        $request->validate([
            'context_campaign' => ['nullable', 'string', 'max:50'],
        ]);

        $context = $request->query('context_campaign');
        $result = $campaigns->getAllowedCampaignsForUser(
            $request->user(),
            is_string($context) && $context !== '' ? $context : null,
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
            'campaigns' => $result->data['campaigns'] ?? [],
            'source' => $result->data['source'] ?? null,
            'server_campaign_code' => $result->data['server_campaign_code'] ?? null,
        ]);
    }

    /**
     * Persist softphone (VICIdial) campaign only — does not change CRM session('campaign').
     */
    public function selectCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['required', 'string', 'max:50'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
        ]);

        $request->session()->put('vicidial_campaign', $validated['campaign']);
        $request->session()->put(
            'vicidial_campaign_name',
            $validated['campaign_name'] ?? $validated['campaign'],
        );

        return response()->json(['success' => true]);
    }
}
