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

        $campaign = TelephonyCampaignResolver::resolveSelected($request, $validated['campaign'] ?? null);
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
                'status' => null,
                'agent_state' => null,
                'message' => $result->message,
            ]);
        }

        $status = $this->extractStatusSnapshot((array) data_get($result->data, 'rows', []));
        if ($status === null) {
            return response()->json([
                'success' => true,
                'active' => false,
                'status' => null,
                'agent_state' => null,
                'message' => null,
            ]);
        }

        $statusCode = strtoupper((string) ($status['status'] ?? ''));
        $agentState = $this->deriveAgentState($statusCode);
        if (! in_array($statusCode, ['INCALL', 'QUEUE'], true)) {
            return response()->json([
                'success' => true,
                'active' => false,
                'status' => $statusCode,
                'agent_state' => $agentState,
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
            'agent_state' => $agentState,
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
        $headerIndex = [];

        foreach ($rows as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            if ($this->looksLikeHeaderRow($row)) {
                $headerIndex = $this->buildHeaderIndex($row);

                continue;
            }

            $status = strtoupper(trim((string) $this->valueFromRow($row, $headerIndex, ['status'], 0)));
            if ($status === '' || $status === 'STATUS') {
                continue;
            }

            return [
                'status' => $status,
                'lead_id' => $this->normalizeNullable($this->valueFromRow($row, $headerIndex, ['lead_id', 'leadid'], 2)),
                'phone_number' => $this->normalizeNullable($this->valueFromRow($row, $headerIndex, ['phone_number', 'phone'], 10)),
            ];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function looksLikeHeaderRow(array $row): bool
    {
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $row);

        return in_array('status', $headers, true)
            && (in_array('lead_id', $headers, true) || in_array('leadid', $headers, true) || in_array('phone_number', $headers, true));
    }

    /**
     * @param  array<int, string>  $row
     * @return array<string, int>
     */
    private function buildHeaderIndex(array $row): array
    {
        $index = [];
        foreach ($row as $position => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if ($normalized !== '') {
                $index[$normalized] = $position;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $headerIndex
     * @param  array<int, string>  $headerNames
     */
    private function valueFromRow(array $row, array $headerIndex, array $headerNames, int $fallbackIndex): mixed
    {
        foreach ($headerNames as $headerName) {
            if (array_key_exists($headerName, $headerIndex)) {
                return $row[$headerIndex[$headerName]] ?? null;
            }
        }

        return $row[$fallbackIndex] ?? null;
    }

    private function normalizeNullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function deriveAgentState(string $statusCode): string
    {
        if (in_array($statusCode, ['INCALL', 'QUEUE'], true)) {
            return 'in_call';
        }

        if (str_starts_with($statusCode, 'PAUSED') || $statusCode === 'PAUSE' || $statusCode === 'PAUSED') {
            return 'paused';
        }

        return 'ready';
    }
}
