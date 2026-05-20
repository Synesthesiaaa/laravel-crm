<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\LeadHydrationService;
use App\Services\Telephony\TelephonyCampaignResolver;
use App\Services\Telephony\VicidialNonAgentApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveLeadController extends Controller
{
    public function __invoke(
        Request $request,
        VicidialNonAgentApiService $nonAgentApi,
        LeadHydrationService $leadHydrationService,
    ): JsonResponse {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $nonAgentApi->execute($request->user(), $campaign, 'agent_status', [
            'agent_user' => (string) $request->user()->vici_user,
            'stage' => 'pipe',
            'header' => 'YES',
            'include_ip' => 'YES',
        ], true);

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'active' => false,
                'message' => $result->message,
            ], 422);
        }

        $status = $this->extractStatusSnapshot((array) data_get($result->data, 'rows', []));
        if ($status === null) {
            return response()->json([
                'success' => true,
                'active' => false,
                'status' => null,
                'message' => null,
            ]);
        }

        $statusCode = strtoupper((string) ($status['status'] ?? ''));
        if (! in_array($statusCode, ['INCALL', 'QUEUE'], true)) {
            return response()->json([
                'success' => true,
                'active' => false,
                'status' => $statusCode,
                'message' => null,
            ]);
        }

        $leadIdText = trim((string) ($status['lead_id'] ?? ''));
        $phoneNumber = trim((string) ($status['phone_number'] ?? ''));
        $leadId = ctype_digit($leadIdText) ? (int) $leadIdText : null;

        $hydrated = $leadHydrationService->hydrate(
            $request->user(),
            $campaign,
            $leadId,
            $phoneNumber !== '' ? $phoneNumber : null,
        );

        return response()->json([
            'success' => true,
            'active' => true,
            'status' => $statusCode,
            'lead_id' => $hydrated['lead_id'] ?? ($leadIdText !== '' ? $leadIdText : null),
            'phone_number' => $hydrated['phone_number'] ?? ($phoneNumber !== '' ? $phoneNumber : null),
            'client_name' => $hydrated['client_name'] ?? null,
            'capture_data' => (array) ($hydrated['capture_data'] ?? []),
            'message' => null,
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array{status:string,lead_id:?string,phone_number:?string}|null
     */
    private function extractStatusSnapshot(array $rows): ?array
    {
        foreach ($rows as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            $status = strtoupper(trim((string) ($row[0] ?? '')));
            if ($status === '' || $status === 'STATUS') {
                continue;
            }

            return [
                'status' => $status,
                'lead_id' => $this->normalizeNullable($row[2] ?? null),
                'phone_number' => $this->normalizeNullable($row[10] ?? null),
            ];
        }

        return null;
    }

    private function normalizeNullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
