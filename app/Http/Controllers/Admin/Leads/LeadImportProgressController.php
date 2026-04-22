<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Http\Controllers\Controller;
use App\Models\LeadList;
use App\Services\Leads\LeadImportProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadImportProgressController extends Controller
{
    public function show(Request $request, LeadList $list, string $runId): JsonResponse
    {
        $this->authorize('import', $list);

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $runId)) {
            return response()->json(['status' => 'unknown', 'message' => 'Invalid import id.'], 422);
        }

        $tracker = app(LeadImportProgressTracker::class);
        $payload = $tracker->get($runId);

        if ($payload === null) {
            return response()->json([
                'run_id' => $runId,
                'list_id' => $list->id,
                'status' => 'unknown',
                'message' => 'No import progress found. It may have finished long ago or the cache expired.',
            ]);
        }

        if ((int) ($payload['list_id'] ?? 0) !== (int) $list->id) {
            abort(403, 'This import does not belong to this list.');
        }

        if ((int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id && ! $request->user()->isSuperAdmin()) {
            abort(403, 'You can only view your own import progress.');
        }

        return response()->json($payload);
    }
}
