<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\VicidialCallHistorySyncState;
use App\Services\Telephony\VicidialCallHistorySyncService;
use Carbon\Carbon;
use Illuminate\Bus\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncVicidialCallHistoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public int $crmCampaignId,
        public ?string $from = null,
        public ?string $to = null,
        public ?int $serverId = null,
    ) {
        $this->tries = max(1, (int) config('vicidial.call_history_sync.retry_times', 3));
        $this->timeout = max(1, (int) config('vicidial.call_history_sync.job_timeout_seconds', 120));
        $this->uniqueFor = $this->timeout + 300;
        $this->onQueue('telephony');
    }

    public function uniqueId(): string
    {
        return implode('|', [
            'vicidial-call-history',
            $this->serverId ?? 'mapped',
            $this->crmCampaignId,
            $this->from ?? 'recent',
            $this->to ?? 'now',
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 300];
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('vicidial-call-history-scope:'.$this->crmCampaignId))
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(VicidialCallHistorySyncService $service): void
    {
        $campaign = Campaign::query()->findOrFail($this->crmCampaignId);
        $result = $service->sync(
            $campaign,
            $this->from !== null ? Carbon::parse($this->from) : null,
            $this->to !== null ? Carbon::parse($this->to) : null,
        );

        if (! $result->success && $result->retryable) {
            throw new \RuntimeException($result->message ?? 'VICIdial call history synchronization failed.');
        }
    }

    public function failed(Throwable $exception): void
    {
        $state = VicidialCallHistorySyncState::query()
            ->where('crm_campaign_id', $this->crmCampaignId)
            ->when($this->serverId !== null, fn ($query) => $query->where('vicidial_server_id', $this->serverId))
            ->latest('id')
            ->first();
        if ($state === null) {
            return;
        }

        $state->update([
            'status' => VicidialCallHistorySyncState::STATUS_FAILED,
            'last_failed_at' => now(),
            'last_error_classification' => $state->last_error_classification ?? 'JOB_FAILED',
            'last_error_message' => $state->last_error_message ?? 'Call history synchronization job failed after retrying.',
        ]);
    }
}
