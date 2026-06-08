<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DispositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispositionController extends Controller
{
    public function __invoke(Request $request, DispositionService $dispositionService): JsonResponse
    {
        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');
        $codes = $dispositionService->getCodesForCampaign($campaign);

        return response()->json(['success' => true, 'codes' => $codes]);
    }
}
