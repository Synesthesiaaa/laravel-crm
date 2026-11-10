<?php

namespace App\Services\Email;

use App\Jobs\DispatchEmailCampaign;
use App\Models\EmailCampaign;
use App\Models\User;
use App\Services\EmailCampaignQueueStatus;
use App\Services\EmailCampaignSendingPolicy;
use App\Services\EmailSmtpService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CampaignLifecycle
{
    public const TERMINAL = ['completed', 'partially_failed', 'failed', 'cancelled'];

    public function __construct(private CampaignApproval $approval) {}

    public function schedule(User $actor, EmailCampaign $campaign, string $local, string $timezone, ?string $offset = null): void
    {
        $instant = $this->instant($local, $timezone, $offset);
        if ($instant->lessThanOrEqualTo(now())) {
            $this->invalid('Choose a future schedule.');
        }
        $this->mutate($actor, $campaign, function (EmailCampaign $locked) use ($actor, $instant, $local, $timezone): void {
            $this->state($locked, ['draft']);
            $this->approval->freeze($locked, $actor->id);
            $locked->forceFill(['status' => 'scheduled', 'scheduled_at' => $instant->utc(), 'schedule_timezone' => $timezone, 'schedule_local' => $local])->save();
        }, 'scheduled');
    }

    public function start(User $actor, EmailCampaign $campaign): void
    {
        $this->ready();
        $this->mutate($actor, $campaign, function (EmailCampaign $locked) use ($actor): void {
            $this->state($locked, ['draft']);
            $this->approval->freeze($locked, $actor->id);
            $this->enqueue($locked);
        }, 'queued');
    }

    public function due(int $campaignId): void
    {
        $this->ready();
        DB::transaction(function () use ($campaignId): void {
            $campaign = EmailCampaign::lockForUpdate()->findOrFail($campaignId);
            if ($campaign->status !== 'scheduled' || $campaign->scheduled_at->isFuture()) {
                return;
            }
            // Verify each frozen recipient and current policy again; scheduled approval is never bypassed.
            if ((int) $campaign->delivery_snapshot_version === 3) {
                foreach ($campaign->recipients()->orderBy('id')->lazyById(250) as $recipient) {
                    $this->approval->verify($campaign, $recipient);
                }
            } else {
                $result = app(\App\Services\EmailCampaignPreflightService::class)->inspect($campaign);
                if ($result['errors'] || ! hash_equals((string) $campaign->manifest_hash, $result['hash'])) {
                    $this->invalid('Scheduled documents or approved settings need review.');
                }
            }
            $this->enqueue($campaign);
        });
    }

    public function pause(User $actor, EmailCampaign $campaign): void
    {
        $this->mutate($actor, $campaign, function (EmailCampaign $locked): void {
            $this->state($locked, ['scheduled', 'queued', 'processing', 'sending']);
            $locked->forceFill(['status' => 'paused', 'generation' => $locked->generation + 1, 'pause_reason' => 'Paused by operator'])->save();
        }, 'paused');
    }

    public function resume(User $actor, EmailCampaign $campaign): void
    {
        $this->ready();
        $this->mutate($actor, $campaign, function (EmailCampaign $locked): void {
            $this->state($locked, ['paused']);
            if (! $locked->delivery_snapshot) {
                $this->invalid('Restore the approved campaign before resuming.');
            }
            if ($locked->scheduled_at?->isFuture() && ! $locked->started_at) {
                $locked->forceFill(['status' => 'scheduled', 'pause_reason' => null])->save();

                return;
            }
            $this->enqueue($locked);
        }, 'resumed');
    }

    public function cancel(User $actor, EmailCampaign $campaign): void
    {
        $this->mutate($actor, $campaign, function (EmailCampaign $locked): void {
            $this->state($locked, ['draft', 'scheduled', 'queued', 'processing', 'sending', 'paused', 'needs_attention']);
            $locked->forceFill(['status' => 'cancelled', 'generation' => $locked->generation + 1, 'completed_at' => now()])->save();
            app(\App\Services\EmailCampaignRecipientTransitionService::class)->transitionAll($locked->id, 'pending', 'cancelled');
        }, 'cancelled');
    }

    public function retry(User $actor, EmailCampaign $campaign): void
    {
        $this->ready();
        $this->mutate($actor, $campaign, function (EmailCampaign $locked): void {
            $this->state($locked, ['partially_failed', 'failed', 'needs_attention']);
            if (! $locked->delivery_snapshot) {
                $this->invalid('No approved snapshot exists for retry.');
            }
            if (! $locked->recipients()->where('status', 'blocked')->exists()) {
                $this->invalid('No safe pre-submission failures are eligible. Reconcile uncertain outcomes first.');
            }
            app(\App\Services\EmailCampaignRecipientTransitionService::class)->transitionAll($locked->id, 'blocked', 'pending', ['error' => null]);
            $this->enqueue($locked);
        }, 'retried');
    }

    public function ready(): void
    {
        // Unit tests can only use non-sending transports; never enable real SMTP with a testing flag.
        if (app()->runningUnitTests() && in_array(config('mail.default'), ['array', 'log'], true)) {
            return;
        }
        if (! app(EmailCampaignSendingPolicy::class)->allowsLive()) {
            $this->invalid('Live campaign delivery is disabled by the environment policy.');
        }
        if (! app(EmailSmtpService::class)->isReady()) {
            $this->invalid('Configure and test SMTP before starting campaigns.');
        }
        if ($problem = app(EmailCampaignQueueStatus::class)->inspect()['problem']) {
            $this->invalid($problem);
        }
    }

    public function instant(string $local, string $timezone, ?string $offset): CarbonImmutable
    {
        if (! in_array($timezone, timezone_identifiers_list(), true) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $local)) {
            $this->invalid('Use a valid local date/time and IANA timezone.');
        }

        try {
            $wall = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $local, 'UTC');
            if ($wall->format('Y-m-d\TH:i') !== $local) {
                $this->invalid('The scheduled date is invalid.');
            }
            $zone = new \DateTimeZone($timezone);
            $transitions = $zone->getTransitions($wall->timestamp - 172800, $wall->timestamp + 172800);
            $offsets = array_unique(array_column($transitions, 'offset'));
            $candidates = [];
            foreach ($offsets as $seconds) {
                $candidate = $wall->subSeconds($seconds)->setTimezone($zone);
                if ($candidate->format('Y-m-d\TH:i') === $local && ($offset === null || $candidate->format('P') === $offset)) {
                    $candidates[$candidate->timestamp] = $candidate;
                }
            }
            if (count($candidates) !== 1) {
                $this->invalid('This time is missing or ambiguous due to daylight saving. Choose another time or an explicit UTC offset.');
            }

            return array_values($candidates)[0];
        } catch (\InvalidArgumentException|\Carbon\Exceptions\InvalidFormatException) {
            $this->invalid('The scheduled date/time is invalid.');
        }
    }

    private function enqueue(EmailCampaign $campaign): void
    {
        $generation = $campaign->generation + 1;
        $campaign->forceFill(['status' => 'queued', 'generation' => $generation, 'started_at' => now(), 'completed_at' => null, 'pause_reason' => null, 'progress_at' => now()])->save();
        DB::table('email_dispatch_intents')->insert(['email_campaign_id' => $campaign->id, 'generation' => $generation, 'status' => 'pending', 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DispatchEmailCampaign::dispatch($campaign->id, $generation)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))->afterCommit();
        app(\App\Services\EmailCampaignRecipientTransitionService::class)->reconcile($campaign->id);
    }

    private function mutate(User $actor, EmailCampaign $campaign, \Closure $action, string $event): void
    {
        Gate::forUser($actor)->authorize('view', $campaign);
        DB::transaction(function () use ($actor, $campaign, $action, $event): void {
            $locked = EmailCampaign::lockForUpdate()->findOrFail($campaign->id);
            Gate::forUser($actor)->authorize('view', $locked);
            $action($locked);
            activity('email-campaign')->causedBy($actor)->performedOn($locked)->event($event)->log('Campaign '.$event);
        });
    }

    private function state(EmailCampaign $campaign, array $allowed): void
    {
        if (! in_array($campaign->status, $allowed, true)) {
            $this->invalid('This action is not available in the campaign’s current state.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['campaign' => $message]);
    }
}
