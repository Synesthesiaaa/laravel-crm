<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\SupervisorOperationalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorAgentsController extends Controller
{
    public function __construct(
        protected SupervisorOperationalService $operationalService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->operationalService->snapshot($request));
    }
}
