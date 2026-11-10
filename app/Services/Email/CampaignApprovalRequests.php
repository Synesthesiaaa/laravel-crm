<?php

namespace App\Services\Email;

use App\Jobs\ApproveEmailCampaign;
use App\Models\EmailCampaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CampaignApprovalRequests
{
    public function request(User $actor, EmailCampaign $campaign, string $action, array $schedule = []): void
    {
        Gate::forUser($actor)->authorize('view', $campaign);
        DB::transaction(function () use ($actor, $campaign, $action, $schedule): void {
            $locked = EmailCampaign::lockForUpdate()->findOrFail($campaign->id);
            Gate::forUser($actor)->authorize('view', $locked);
            if ($locked->status !== 'draft' || $locked->audience_status !== 'ready' || in_array($locked->approval_state, ['queued', 'processing'], true)) {
                throw ValidationException::withMessages(['campaign' => 'Approval requires a ready draft with no preparation already in progress.']);
            }
            $locked->forceFill(['generation' => $locked->generation + 1, 'approval_state' => 'queued', 'approval_requested_at' => now(), 'approval_error' => null,
                'approval_request' => ['actor_id' => $actor->id, 'action' => $action, 'schedule' => $schedule, 'draft_digest' => $this->digest($locked)],
            ])->save();
            ApproveEmailCampaign::dispatch($locked->id, $locked->generation)->afterCommit();
        });
    }

    public function digest(EmailCampaign $campaign): string
    {
        $campaign->load('template');

        return hash('sha256', json_encode([$campaign->only(['name', 'email_template_id', 'recipient_count', 'batch_size', 'delay_seconds', 'purpose', 'attachment_mode', 'tracking_opens', 'tracking_clicks']), app(TemplateComposer::class)->snapshot($campaign->template)], JSON_THROW_ON_ERROR));
    }
}
