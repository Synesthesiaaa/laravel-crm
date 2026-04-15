<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telephony\TelephonyCampaignResolver;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorTelephonyController extends Controller
{
    public function __construct(
        protected VicidialProxyService $agentApi,
    ) {}

    public function monitor(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'blind_monitor', ['stage' => 'MONITOR']);
    }

    public function whisper(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'blind_monitor', ['stage' => 'WHISPER']);
    }

    public function forcePause(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'external_pause', ['value' => 'PAUSE']);
    }

    public function forceLogout(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'logout', ['value' => 'LOGOUT']);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_type' => ['required', 'string', 'in:USER,USER_GROUP,CAMPAIGN'],
            'recipient' => ['required', 'string', 'max:50'],
            'notification_text' => ['nullable', 'string', 'max:255'],
            'show_confetti' => ['nullable', 'boolean'],
        ]);

        $explicit = $request->input('campaign');
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            is_string($explicit) && $explicit !== '' ? (string) $explicit : null,
        );
        $payload = [
            'recipient_type' => $validated['recipient_type'],
            'recipient' => $validated['recipient'],
            'notification_text' => $validated['notification_text'] ?? '',
            'show_confetti' => ! empty($validated['show_confetti']) ? 'Y' : 'N',
        ];

        $result = $this->agentApi->execute($request->user(), $campaign, 'send_notification', [
            'value' => '',
            'query' => $payload,
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'raw_response' => $result['raw_response'],
        ], $result['success'] ? 200 : 422);
    }

    protected function agentAction(Request $request, string $action, array $extra = []): JsonResponse
    {
        $validated = $request->validate([
            'agent_user_id' => ['required', 'integer'],
            'campaign' => ['nullable', 'string', 'max:50'],
            'phone_login' => ['nullable', 'string', 'max:20'],
            'session_id' => ['nullable', 'string', 'max:20'],
            'server_ip' => ['nullable', 'string', 'max:20'],
        ]);

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $agent = User::findOrFail((int) $validated['agent_user_id']);

        $query = array_merge([
            'agent_user' => (string) ($agent->vici_user ?? ''),
            'phone_login' => $validated['phone_login'] ?? '',
            'session_id' => $validated['session_id'] ?? '',
            'server_ip' => $validated['server_ip'] ?? '',
        ], $extra);

        $result = $this->agentApi->execute($request->user(), $campaign, $action, [
            'value' => $query['value'] ?? '',
            'query' => array_filter($query, static fn ($v) => $v !== ''),
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'raw_response' => $result['raw_response'],
        ], $result['success'] ? 200 : 422);
    }
}
