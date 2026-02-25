<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\CallOrchestrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(
        protected CallOrchestrationService $orchestration
    ) {}

    /**
     * Start outbound call. Creates call session and originates via VICIdial.
     */
    public function dial(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string', 'max:50'],
            'lead_id' => ['nullable', 'integer'],
            'phone_code' => ['nullable', 'string', 'max:5'],
        ]);

        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');
        $phoneNumber = $request->input('phone_number') ?: $request->query('phone_number') ?: $request->input('phone');

        if (empty($phoneNumber)) {
            return response()->json(['success' => false, 'message' => 'Phone number is required'], 422);
        }

        $result = $this->orchestration->startOutboundCall(
            $request->user(),
            $campaign,
            $phoneNumber,
            $request->input('lead_id') ? (int) $request->input('lead_id') : null,
            $request->input('phone_code', '1')
        );

        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        return response()->json([
            'success' => true,
            'session_id' => $result->data['session_id'],
        ]);
    }

    /**
     * Hang up the agent's active call.
     */
    public function hangup(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id') ? (int) $request->input('session_id') : null;
        $result = $this->orchestration->hangup($request->user(), $sessionId);

        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get agent's current active call state and disposition status (for UI sync).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->orchestration->getActiveSession($user);
        $pendingDisposition = $this->orchestration->getPendingDispositionSession($user);

        if ($session) {
            $duration = $session->answered_at
                ? (int) now()->diffInSeconds($session->answered_at)
                : 0;

            return response()->json([
                'success' => true,
                'active' => true,
                'disposition_pending' => false,
                'call' => [
                    'session_id' => $session->id,
                    'phone_number' => $session->phone_number,
                    'status' => $session->status,
                    'lead_id' => $session->lead_id,
                    'duration_seconds' => $duration,
                ],
            ]);
        }

        if ($pendingDisposition) {
            return response()->json([
                'success' => true,
                'active' => false,
                'disposition_pending' => true,
                'pending_call' => [
                    'session_id' => $pendingDisposition->id,
                    'phone_number' => $pendingDisposition->phone_number,
                    'status' => $pendingDisposition->status,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'active' => false,
            'disposition_pending' => false,
            'call' => null,
        ]);
    }
}
