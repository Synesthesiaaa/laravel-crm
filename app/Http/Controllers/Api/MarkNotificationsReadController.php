<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MarkNotificationsReadController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
