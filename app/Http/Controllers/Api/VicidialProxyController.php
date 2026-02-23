<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VicidialProxyController extends Controller
{
    public function __invoke(Request $request, VicidialProxyService $proxy, CampaignService $campaignService): JsonResponse
    {
        $action = $request->query('action');
        if (empty($action)) {
            return response()->json(['success' => false, 'message' => 'Missing action']);
        }
        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');
        $result = $proxy->execute($request->user(), $campaign, $action, [
            'value' => $request->query('value', ''),
            'phone_code' => $request->query('phone_code', '1'),
            'phone_number' => $request->query('phone_number', ''),
        ]);
        return response()->json([
            'success' => $result['success'],
            'raw_response' => $result['raw_response'],
            'message' => $result['message'],
        ]);
    }
}
