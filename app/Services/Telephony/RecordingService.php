<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class RecordingService
{
    public function __construct(
        protected VicidialProxyService $agentApi,
        protected VicidialNonAgentApiService $nonAgentApi
    ) {}

    public function startRecording(User $user, string $campaign, ?string $stage = null): OperationResult
    {
        $query = [];
        if ($stage) {
            $query['stage'] = $stage;
        }

        $result = $this->agentApi->execute($user, $campaign, 'recording', [
            'value' => 'START',
            'query' => $query,
        ]);

        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Failed to start recording.');
        }

        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }

    public function stopRecording(User $user, string $campaign): OperationResult
    {
        $result = $this->agentApi->execute($user, $campaign, 'recording', ['value' => 'STOP']);
        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Failed to stop recording.');
        }
        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }

    public function getRecordingStatus(User $user, string $campaign): OperationResult
    {
        $result = $this->agentApi->execute($user, $campaign, 'recording', ['value' => 'STATUS']);
        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Unable to fetch recording status.');
        }
        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }

    public function lookupRecordings(User $user, string $campaign, array $filters): OperationResult
    {
        $params = array_filter([
            'agent_user' => $filters['agent_user'] ?? null,
            'lead_id' => $filters['lead_id'] ?? null,
            'date' => $filters['date'] ?? null,
            'extension' => $filters['extension'] ?? null,
            'stage' => $filters['stage'] ?? 'pipe',
            'header' => $filters['header'] ?? 'YES',
            'duration' => $filters['duration'] ?? 'Y',
        ], static fn ($v) => $v !== null && $v !== '');

        return $this->nonAgentApi->execute($user, $campaign, 'recording_lookup', $params, true);
    }
}
