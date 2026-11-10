<?php

namespace App\Services\Email;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignAttempt;
use App\Models\EmailTemplate;
use App\Services\EmailCampaignDeliveryService;
use App\Services\EmailCampaignRecipientTransitionService;
use App\Services\EmailCampaignSendingPolicy;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagedEmailDelivery
{
    public function __construct(private EmailCampaignRecipientTransitionService $transitions, private CampaignApproval $approval, private TemplateComposer $composer) {}

    public function send(int $campaignId, int $recipientId, int $generation): void
    {
        $campaign = EmailCampaign::find($campaignId);
        if (! $campaign) {
            return;
        }
        if ((int) $campaign->delivery_snapshot_version < 3) {
            app(EmailCampaignDeliveryService::class)->send($campaignId, $recipientId, $generation);

            return;
        }
        $claimed = DB::transaction(function () use ($campaignId, $recipientId, $generation): ?array {
            $recipient = $this->transitions->claim($campaignId, $recipientId, $generation);
            if (! $recipient) {
                return null;
            }
            $attempt = EmailCampaignAttempt::create([
                'id' => (string) Str::uuid(), 'email_campaign_id' => $campaignId,
                'email_campaign_recipient_id' => $recipientId, 'generation' => $generation, 'status' => 'prepared',
                'provider' => $recipient->campaign->delivery_snapshot['provider'] ?? 'smtp',
                'attempt_number' => EmailCampaignAttempt::where('email_campaign_recipient_id', $recipientId)->count() + 1,
            ]);

            return [$recipient, $attempt];
        });
        if (! $claimed) {
            return;
        }
        [$recipient, $attempt] = $claimed;
        $submitted = false;

        try {
            $campaign->refresh();
            $approver = \App\Models\User::find($campaign->approved_by_user_id);
            if (! $approver || ! (new \App\Policies\EmailCampaignPolicy)->view($approver, $campaign)) {
                throw ValidationException::withMessages(['campaign' => 'The approving administrator no longer has access to this campaign.']);
            }
            $values = $this->approval->verify($campaign, $recipient);
            $snapshot = $campaign->delivery_snapshot;
            if (($snapshot['transport']['transport'] ?? '') !== 'array' && ! app(EmailCampaignSendingPolicy::class)->allowsLive()) {
                throw ValidationException::withMessages(['campaign' => 'Live delivery is disabled by the environment policy.']);
            }
            $contactWithdrawn = $recipient->email_contact_id && DB::table('email_contacts')->where('id', $recipient->email_contact_id)->whereNotIn('consent_status', ['opted_in', 'asserted'])->exists();
            if ($contactWithdrawn || app(SuppressionService::class)->blocked($recipient->email, $campaign->purpose)) {
                $attempt->update(['status' => 'skipped', 'error_code' => 'suppressed_or_consent_withdrawn']);
                $this->transitions->transition($campaignId, $recipientId, 'sending', 'skipped');

                return;
            }
            $template = new EmailTemplate($snapshot['template']);
            $subject = $this->composer->subject($template, $values);
            $tracking = app(EmailTrackingService::class);
            $html = $tracking->decorate($this->composer->render($template, $values), $campaign, $recipient, $attempt->id);
            $unsubscribe = $tracking->unsubscribeUrl($campaign, $recipient);
            $html .= '<p style="font-size:12px">From '.e($snapshot['sender'][1]).' · <a href="'.e($unsubscribe).'">Unsubscribe</a></p>';
            $mailer = Mail::build($snapshot['transport']);
            // Persist transport entry before submitting; a crash from this point is never blindly retried.
            $attempt->update(['status' => 'submitting', 'entered_transport_at' => now()]);
            $submitted = true;
            $mailer->html($html, function (Message $message) use ($snapshot, $recipient, $subject, $unsubscribe, $attempt): void {
                $message->from(...$snapshot['sender'])->to($recipient->email, $recipient->name ?: null)->subject($subject);
                $headers = $message->getSymfonyMessage()->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                if ($snapshot['provider'] === 'postmark') {
                    $headers->addTextHeader('X-PM-Metadata-attempt', $attempt->id);
                    $headers->addTextHeader('X-PM-Message-Stream', $snapshot['postmark_message_stream'] ?? 'broadcast');
                }
            });
            DB::transaction(function () use ($attempt, $campaignId, $recipientId): void {
                // Provider feedback may already have recorded acceptance. Never erase it.
                EmailCampaign::whereKey($campaignId)->lockForUpdate()->first();
                \App\Models\EmailCampaignRecipient::whereKey($recipientId)->lockForUpdate()->first();
                EmailCampaignAttempt::whereKey($attempt->id)->whereIn('status', ['prepared', 'submitting'])->update(['status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now()]);
                $this->transitions->transition($campaignId, $recipientId, 'sending', 'accepted', ['sent_at' => now()]);
            });
        } catch (\Throwable) {
            EmailCampaignAttempt::whereKey($attempt->id)->whereNotIn('status', ['accepted', 'delivered', 'bounced', 'complained'])->update([
                'status' => $submitted ? 'uncertain' : 'blocked', 'error_code' => $submitted ? 'transport_outcome_unknown' : 'pre_submission_verification', 'updated_at' => now(),
            ]);
            $this->transitions->transition($campaignId, $recipientId, 'sending', $submitted ? 'uncertain' : 'blocked', ['error' => $submitted ? 'Transport outcome uncertain; obtain provider evidence before retrying.' : 'Pre-send verification failed. Review consent, approved content and configuration.']);
        }
    }

    public function interrupted(int $campaignId, int $recipientId, ?int $generation = null): void
    {
        DB::transaction(function () use ($campaignId, $recipientId, $generation): void {
            EmailCampaign::whereKey($campaignId)->lockForUpdate()->first();
            $recipient = \App\Models\EmailCampaignRecipient::whereKey($recipientId)->lockForUpdate()->first();
            $attempt = EmailCampaignAttempt::where('email_campaign_recipient_id', $recipientId)->latest('attempt_number')->lockForUpdate()->first();
            if ($generation !== null && (! $attempt || (int) $attempt->generation !== $generation || $recipient?->claimed_at?->greaterThan(now()->subSeconds(59)))) {
                return;
            }
            if ($attempt && in_array($attempt->status, ['accepted', 'delivered', 'bounced', 'complained'], true)) {
                return;
            }
            $uncertain = $attempt?->entered_transport_at !== null;
            $attempt?->update(['status' => $uncertain ? 'uncertain' : 'blocked', 'error_code' => 'worker_interrupted']);
            $this->transitions->transition($campaignId, $recipientId, 'sending', $uncertain ? 'uncertain' : 'blocked', ['error' => $uncertain ? 'Worker interrupted after transport entry; reconcile with provider.' : 'Worker interrupted before submission; verify and retry.']);
        });
    }
}
