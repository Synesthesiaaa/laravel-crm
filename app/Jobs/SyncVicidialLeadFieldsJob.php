<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Telephony\LeadService;
use App\Services\Telephony\TelephonyLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVicidialLeadFieldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 10;

    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(
        public int $userId,
        public string $campaign,
        public array $fields,
    ) {
        // Capture is already stored locally; remote VICIdial sync must not hold
        // the agent's form request open or depend on Redis/Horizon availability.
        $this->onConnection('deferred');
    }

    public function handle(LeadService $leadService, TelephonyLogger $telephonyLogger): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        try {
            $result = $leadService->updateFields($user, $this->campaign, $this->fields);
            if (! $result->success) {
                $telephonyLogger->warning('SyncVicidialLeadFieldsJob', 'Vicidial update_fields push failed', [
                    'campaign' => $this->campaign,
                    'lead_id' => $this->fields['lead_id'] ?? null,
                    'message' => $result->message,
                    'mapped_fields' => array_keys($this->fields),
                ]);
            }
        } catch (\Throwable $e) {
            $telephonyLogger->warning('SyncVicidialLeadFieldsJob', 'Vicidial update_fields push threw exception', [
                'campaign' => $this->campaign,
                'lead_id' => $this->fields['lead_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
