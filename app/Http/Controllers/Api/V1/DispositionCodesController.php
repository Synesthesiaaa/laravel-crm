<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DispositionCodeResource;
use App\Repositories\DispositionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispositionCodesController extends Controller
{
    public function __invoke(Request $request, DispositionRepository $repository): JsonResponse
    {
        $campaign = $request->query('campaign', '');
        if ($campaign === '') {
            return response()->json(['message' => 'Campaign is required'], 422);
        }

        $codes = $repository->getForCampaign($campaign);

        return response()->json([
            'success' => true,
            'data' => DispositionCodeResource::collection($codes),
        ]);
    }
}
