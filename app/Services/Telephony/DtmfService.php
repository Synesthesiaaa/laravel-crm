<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class DtmfService
{
    public function __construct(protected VicidialProxyService $agentApi) {}

    public function send(User $user, string $campaign, string $digits): OperationResult
    {
        $encoded = strtoupper(trim($digits));
        if ($encoded === '') {
            return OperationResult::failure('DTMF digits are required.');
        }

        // Normalize human input to VICIdial format.
        $encoded = str_replace('#', 'P', $encoded);
        $encoded = str_replace('*', 'S', $encoded);

        if (!preg_match('/^[0-9PSQ]+$/', $encoded)) {
            return OperationResult::failure('Invalid DTMF string. Allowed: 0-9, *, #, Q.');
        }

        $result = $this->agentApi->execute($user, $campaign, 'send_dtmf', ['value' => $encoded]);
        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Unable to send DTMF.');
        }

        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }
}
