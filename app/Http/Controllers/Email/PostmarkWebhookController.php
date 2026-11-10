<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Services\Email\PostmarkEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostmarkWebhookController extends Controller
{
    public function __invoke(Request $request, PostmarkEventService $events): JsonResponse
    {
        $events->receive($request);

        return response()->json(['received' => true], 202);
    }
}
