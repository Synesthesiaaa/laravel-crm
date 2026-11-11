<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\User;
use App\Services\Email\CampaignApprovalRequests;
use App\Services\Email\CampaignLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveEmailCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(public int $campaignId, public int $generation)
    {
        $this->onConnection(config('email_campaigns.preparation_connection'))->onQueue(config('email_campaigns.preparation_queue'));
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        try {
            DB::transaction(function (): void {
                $campaign = EmailCampaign::lockForUpdate()->find($this->campaignId);
                if (! $campaign || $campaign->generation !== $this->generation || $campaign->status !== 'draft' || ! in_array($campaign->approval_state, ['queued', 'processing'], true)) {
                    return;
                }
                $request = $campaign->approval_request;
                $actor = User::find($request['actor_id'] ?? null);
                if (! $actor || ! (new \App\Policies\EmailCampaignPolicy)->view($actor, $campaign) || ! hash_equals($request['draft_digest'] ?? '', app(CampaignApprovalRequests::class)->digest($campaign))) {
                    throw ValidationException::withMessages(['campaign' => 'The draft or approving account changed. Review and request approval again.']);
                }
                $campaign->forceFill(['approval_state' => 'processing'])->save();
                $lifecycle = app(CampaignLifecycle::class);
                if ($request['action'] === 'schedule') {
                    $schedule = $request['schedule'];
                    $lifecycle->schedule($actor, $campaign, $schedule['scheduled_local'], $schedule['timezone'], $schedule['utc_offset'] ?? null);
                } else {
                    $lifecycle->start($actor, $campaign);
                }
                $campaign->refresh()->forceFill(['approval_state' => 'approved', 'approval_request' => null, 'approval_error' => null])->save();
            });
        } catch (ValidationException $error) {
            $safe = implode(' ', array_slice(array_merge(...array_values($error->errors())), 0, 3));
            EmailCampaign::whereKey($this->campaignId)->where('generation', $this->generation)->where('status', 'draft')->update(['approval_state' => 'failed', 'approval_error' => mb_substr($safe, 0, 500)]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        EmailCampaign::whereKey($this->campaignId)->where('generation', $this->generation)->where('status', 'draft')->update(['approval_state' => 'failed', 'approval_error' => 'Approval preparation was interrupted. Check the preparation worker and request approval again.']);
    }
}
