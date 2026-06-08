<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DispositionService;
use App\Services\Telephony\TelephonyCampaignResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispositionController extends Controller
{
    public function __invoke(Request $request, DispositionService $dispositionService): JsonResponse
    {
        $campaign = TelephonyCampaignResolver::resolveSelected(
            $request,
            is_string($request->query('campaign')) ? (string) $request->query('campaign') : null,
        );
        $codes = $dispositionService->getCodesForCampaign($campaign);

        return response()->json(['success' => true, 'codes' => $codes]);
    }
}
