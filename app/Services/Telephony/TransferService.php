<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Support\OperationResult;

class TransferService
{
    public function __construct(protected VicidialProxyService $agentApi) {}

    public function blindTransfer(User $user, string $campaign, string $phoneNumber): OperationResult
    {
        return $this->transferConference($user, $campaign, 'BLIND_TRANSFER', $phoneNumber);
    }

    public function warmTransfer(User $user, string $campaign, ?string $phoneNumber = null, ?string $ingroup = null, bool $consultative = true): OperationResult
    {
        $query = ['consultative' => $consultative ? 'YES' : 'NO'];
        if ($ingroup) {
            $query['ingroup_choices'] = $ingroup;
        }
        if ($phoneNumber) {
            $query['phone_number'] = $phoneNumber;
        }

        return $this->transferConference($user, $campaign, 'DIAL_WITH_CUSTOMER', $phoneNumber, $query);
    }

    public function localCloser(User $user, string $campaign, string $ingroup, ?string $phoneNumber = null): OperationResult
    {
        $query = ['ingroup_choices' => $ingroup];
        if ($phoneNumber) {
            $query['phone_number'] = $phoneNumber;
        }
        return $this->transferConference($user, $campaign, 'LOCAL_CLOSER', $phoneNumber, $query);
    }

    public function leaveThreeWay(User $user, string $campaign): OperationResult
    {
        return $this->transferConference($user, $campaign, 'LEAVE_3WAY_CALL');
    }

    public function hangupXfer(User $user, string $campaign): OperationResult
    {
        return $this->transferConference($user, $campaign, 'HANGUP_XFER');
    }

    public function hangupBoth(User $user, string $campaign): OperationResult
    {
        return $this->transferConference($user, $campaign, 'HANGUP_BOTH');
    }

    public function leaveVoicemail(User $user, string $campaign): OperationResult
    {
        return $this->transferConference($user, $campaign, 'LEAVE_VM');
    }

    public function parkCustomer(User $user, string $campaign): OperationResult
    {
        return $this->parkCall($user, $campaign, 'PARK_CUSTOMER');
    }

    public function grabCustomer(User $user, string $campaign): OperationResult
    {
        return $this->parkCall($user, $campaign, 'GRAB_CUSTOMER');
    }

    public function parkIvrCustomer(User $user, string $campaign): OperationResult
    {
        return $this->parkCall($user, $campaign, 'PARK_IVR_CUSTOMER');
    }

    public function swapPark(User $user, string $campaign, string $target): OperationResult
    {
        $value = strtoupper($target) === 'XFER' ? 'SWAP_PARK_XFER' : 'SWAP_PARK_CUSTOMER';
        return $this->parkCall($user, $campaign, $value);
    }

    protected function transferConference(
        User $user,
        string $campaign,
        string $value,
        ?string $phoneNumber = null,
        array $query = []
    ): OperationResult {
        $result = $this->agentApi->execute($user, $campaign, 'transfer_conference', [
            'value' => $value,
            'phone_number' => $phoneNumber,
            'query' => $query,
        ]);

        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Transfer request failed.');
        }

        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }

    protected function parkCall(User $user, string $campaign, string $value): OperationResult
    {
        $result = $this->agentApi->execute($user, $campaign, 'park_call', ['value' => $value]);
        if (! $result['success']) {
            return OperationResult::failure($result['message'] ?: 'Park call request failed.');
        }
        return OperationResult::success(['raw_response' => $result['raw_response']]);
    }
}
