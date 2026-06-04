<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WidgetLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WidgetLayoutController extends Controller
{
    /** @var array<int, string> */
    private const ALLOWED_WIDGETS = ['softphone', 'quick_form'];

    public function index(Request $request, WidgetLayoutService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'layouts' => $service->getLayoutsForUser($request->user()),
        ]);
    }

    public function update(string $widget, Request $request, WidgetLayoutService $service): JsonResponse
    {
        $validated = $request->validate([
            'layout' => ['required', 'array'],
            'layout.x' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'layout.y' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'layout.width' => ['nullable', 'numeric', 'min:200', 'max:2400'],
            'layout.height' => ['nullable', 'numeric', 'min:120', 'max:2400'],
            'layout.open' => ['nullable', 'boolean'],
            'layout.controlsHeight' => ['nullable', 'numeric', 'min:120', 'max:1200'],
            'layout.z' => ['nullable', 'integer', 'min:1', 'max:999'],
            'layout.formType' => ['nullable', 'string', 'max:100'],
            'layout.campaign' => ['nullable', 'string', 'max:100'],
        ]);

        validator(
            ['widget' => $widget],
            ['widget' => ['required', 'string', Rule::in(self::ALLOWED_WIDGETS)]],
        )->validate();

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
