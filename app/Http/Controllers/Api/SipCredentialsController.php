<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SipCredentialsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->extension)) {
            return response()->json([
                'success' => false,
                'message' => 'No SIP extension assigned to this account. Contact your administrator.',
            ], 422);
        }

        if (empty($user->sip_password)) {
            return response()->json([
                'success' => false,
                'message' => 'SIP password not configured for this account. Contact your administrator.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'sip_uri' => $user->extension.'@'.config('webrtc.sip_domain'),
            'ws_url' => config('webrtc.asterisk_ws_url'),
            'extension' => $user->extension,
            'password' => $user->sip_password,
            'stun' => config('webrtc.stun_server'),
            'domain' => config('webrtc.sip_domain'),
            'ice_servers' => config('webrtc.ice_servers'),
            'no_answer_timeout' => config('webrtc.no_answer_timeout'),
        ]);
    }
}
