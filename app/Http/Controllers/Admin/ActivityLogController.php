<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityLogEntry $entryFormatter): View
    {
        return view('admin.activity_log', [
            'entries' => $entryFormatter->recent($this->filters($request)),
            'actors' => User::query()
                ->orderBy('full_name')
                ->orderBy('username')
                ->get(['id', 'username', 'full_name']),
        ]);
    }

    public function entries(Request $request, ActivityLogEntry $entryFormatter): JsonResponse
    {
        $validated = $request->validate([
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', 'string', 'max:50'],
            'resource' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'since_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $entries = $entryFormatter->recent($validated, (int) ($validated['limit'] ?? 100));

        return response()->json([
            'data' => $entries->values()->all(),
            'meta' => [
                'count' => $entries->count(),
                'last_id' => $entries->last()['id'] ?? null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->only([
            'actor_id',
            'event',
            'resource',
            'search',
            'from',
            'to',
            'since_id',
        ]);
    }
}
