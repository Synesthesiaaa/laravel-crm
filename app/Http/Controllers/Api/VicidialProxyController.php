<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\CallOrchestrationService;
use App\Services\Telephony\VicidialProxyService;
use App\Support\CallErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VicidialProxyController extends Controller
{
    public function __invoke(Request $request, VicidialProxyService $proxy, CallOrchestrationService $orchestration): JsonResponse
    {
        $action = $request->query('action');
        if (empty($action)) {
            return response()->json(['success' => false, 'message' => 'Missing action']);
        }
        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');

        // Dial actions: delegate to CallOrchestrationService (creates session, state machine)
        $dialActions = ['originate', 'external_dial'];
        $phoneNumber = $request->query('phone_number') ?: $request->query('phone') ?: $request->query('value', '');
        if (in_array($action, $dialActions, true) && $phoneNumber !== '') {
            $result = $orchestration->startOutboundCall(
                $request->user(),
                $campaign,
                $phoneNumber,
                $request->query('lead_id') ? (int) $request->query('lead_id') : null,
                $request->query('phone_code', '1'),
            );
            $payload = [
                'success' => $result->success,
                'session_id' => $result->data['session_id'] ?? null,
                'message' => $result->message,
            ];
            if (! $result->success && is_array($result->data) && isset($result->data['error_code'])) {
                $payload['error'] = $result->data;
            }

            return response()->json($payload);
        }

        // Other actions: pass through to VICIdial proxy
        $result = $proxy->execute($request->user(), $campaign, $action, [
            'value' => $request->query('value', $phoneNumber),
            'phone_code' => $request->query('phone_code', '1'),
            'phone_number' => $phoneNumber,
        ]);
        $out = [
            'success' => $result['success'],
            'raw_response' => $result['raw_response'],
            'message' => $result['message'],
        ];
        if (! $result['success'] && ! empty($result['failure_code'])) {
            $out['error'] = CallErrors::toJson($result['failure_code']);
        }

        return response()->json($out);
    }
}
