<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WidgetLayoutUpdateRequest;
use App\Services\WidgetLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetLayoutController extends Controller
{
    public function index(Request $request, WidgetLayoutService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'layouts' => $service->getLayoutsForUser($request->user()),
        ]);
    }

    public function update(WidgetLayoutUpdateRequest $request, WidgetLayoutService $service): JsonResponse
    {
        $validated = $request->validated();
        $widget = $validated['widget'];

        $layout = $service->saveLayout(
            $request->user(),
            $widget,
            $validated['layout'],
        );

        return response()->json([
            'success' => true,
            'widget' => $widget,
            'layout' => $layout,
        ]);
    }
}
