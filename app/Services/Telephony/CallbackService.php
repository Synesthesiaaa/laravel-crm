<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class CallbackService
{
    public function __construct(protected VicidialNonAgentApiService $nonAgentApi) {}

    public function schedule(
        User $user,
        string $campaign,
        int $leadId,
        string $datetime,
        string $type = 'ANYONE',
        ?string $callbackUser = null,
        ?string $comments = null,
        string $callbackStatus = 'CALLBK',
    ): OperationResult {
        $type = strtoupper($type);
        if (! in_array($type, ['ANYONE', 'USERONLY'], true)) {
            return OperationResult::failure('Invalid callback type.');
        }

        $params = [
            'lead_id' => $leadId,
            'search_method' => 'LEAD_ID',
            'callback' => 'Y',
            'callback_status' => $callbackStatus,
            'callback_datetime' => $datetime,
            'callback_type' => $type,
        ];

        if ($type === 'USERONLY') {
            $params['callback_user'] = $callbackUser ?: (string) $user->vici_user;
        }
        if ($comments) {
            $params['callback_comments'] = $comments;
        }

        return $this->nonAgentApi->execute($user, $campaign, 'update_lead', $params, true);
    }

    public function remove(User $user, string $campaign, int $leadId): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'update_lead', [
            'lead_id' => $leadId,
            'search_method' => 'LEAD_ID',
            'callback' => 'REMOVE',
        ], true);
    }

    public function info(User $user, string $campaign, int $leadId, string $searchLocation = 'ALL'): OperationResult
    {
        return $this->nonAgentApi->execute($user, $campaign, 'lead_callback_info', [
            'lead_id' => $leadId,
            'search_location' => $searchLocation,
            'stage' => 'pipe',
            'header' => 'YES',
        ], true);
    }
}
