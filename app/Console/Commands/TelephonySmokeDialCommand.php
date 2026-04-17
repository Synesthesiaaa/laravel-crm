<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telephony\CallOrchestrationService;
use Illuminate\Console\Command;

class TelephonySmokeDialCommand extends Command
{
    protected $signature = 'telephony:smoke-dial
        {--user-id= : Agent user id}
        {--number= : Destination phone number}
        {--campaign=mbsales : Campaign code}
        {--phone-code=1 : Phone code}';

    protected $description = 'Run a direct CRM -> AMI -> trunk dial smoke test';

    public function __construct(
        protected CallOrchestrationService $orchestration,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = (int) $this->option('user-id');
        $number = trim((string) $this->option('number'));
        $campaign = trim((string) $this->option('campaign'));
        $phoneCode = trim((string) $this->option('phone-code'));

        if ($userId <= 0 || $number === '' || $campaign === '') {
            $this->error('Required: --user-id, --number, --campaign');

            return self::FAILURE;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error("User not found: {$userId}");

            return self::FAILURE;
        }
        if (empty($user->extension)) {
            $this->error("User {$userId} has no extension configured.");

            return self::FAILURE;
        }

        $this->info("Dialing {$number} for user {$user->id} ({$user->extension}) in campaign {$campaign}...");
        $result = $this->orchestration->startOutboundCall(
            $user,
            $campaign,
            $number,
            null,
            $phoneCode,
        );

        if (! $result->success) {
            $this->error('Smoke dial failed: '.($result->message ?? 'Unknown error'));
            if (is_array($result->data)) {
                $this->line(json_encode($result->data));
            }

            return self::FAILURE;
        }

        $sessionId = $result->data['session_id'] ?? null;
        $this->info('Smoke dial started successfully. Session ID: '.($sessionId ?: 'n/a'));

        return self::SUCCESS;
    }
}
