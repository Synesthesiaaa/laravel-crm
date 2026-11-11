<?php

namespace App\Services\Email;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Services\EmailCampaignPreflightService;
use App\Services\EmailCampaignSnapshotAuthenticator;
use App\Services\EmailSmtpService;
use Illuminate\Validation\ValidationException;

class CampaignApproval
{
    public function __construct(private TemplateComposer $composer, private EmailSmtpService $smtp, private EmailCampaignSnapshotAuthenticator $authenticator) {}

    public function freeze(EmailCampaign $campaign, int $actor): void
    {
        if ((int) $campaign->management_version < 3) {
            app(EmailCampaignPreflightService::class)->freeze($campaign, $actor);

            return;
        }
        if ($campaign->needsReview() || $campaign->template->pdf_enabled) {
            $this->invalid('Confidential attachments must use the protected document workflow.');
        }
        if ($campaign->audience_status !== 'ready' || ! $campaign->recipient_count
            || $campaign->recipients()->count() !== $campaign->recipient_count) {
            $this->invalid('Finish and apply a validated audience before approval.');
        }
        [$address, $name] = $this->smtp->sender();
        if (! app()->runningUnitTests() && ! $this->smtp->isReady()) {
            $this->invalid('Configure SMTP before approving or scheduling campaigns.');
        }
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $this->invalid('Configure a valid sender before approval.');
        }
        $settings = $this->smtp->activeSettings();
        $template = $this->composer->snapshot($campaign->template);
        $header = [
            'purpose' => $campaign->purpose, 'attachment_mode' => 'none',
            'template' => $template, 'sender' => [$address, $name],
            'transport' => $settings ? $this->smtp->transportConfiguration($settings) : config('mail.mailers.'.config('mail.default')),
            'provider' => $settings?->provider ?? 'smtp',
            'rate_per_minute' => max(1, min(10000, (int) ($settings?->rate_per_minute ?? config('email_campaigns.rate_per_minute', 60)))),
            'tracking_opens' => (bool) $campaign->tracking_opens,
            'tracking_clicks' => (bool) $campaign->tracking_clicks,
            'tracking' => ['opens' => (bool) $campaign->tracking_opens, 'clicks' => (bool) $campaign->tracking_clicks],
            'postmark_server_id' => $settings?->postmark_server_id,
            'postmark_message_stream' => $settings?->postmark_message_stream,
            'shared_documents' => [],
        ];
        if (($header['transport']['transport'] ?? '') === 'smtp') {
            $header['transport']['timeout'] = 15;
        }
        $hash = hash_init('sha256');
        hash_update($hash, json_encode($header, JSON_THROW_ON_ERROR));
        foreach ($campaign->recipients()->orderBy('id')->lazyById(250) as $recipient) {
            $values = $this->values($recipient);
            if ($recipient->email_contact_id && \Illuminate\Support\Facades\DB::table('email_contacts')->where('id', $recipient->email_contact_id)->whereNotIn('consent_status', ['opted_in', 'asserted'])->exists()) {
                $this->invalid('A contact withdrew permission before approval. Prepare an eligible audience.');
            }
            if (! in_array($values['consent_status'], ['opted_in', 'asserted'], true)
                || ! $values['consent_source'] || ! $values['consent_asserted_at']) {
                $this->invalid('Every marketing recipient needs documented consent or a permission assertion.');
            }
            if (app(SuppressionService::class)->blocked($recipient->email, $campaign->purpose)) {
                $this->invalid('The audience includes a suppressed recipient. Prepare an eligible audience.');
            }
            $this->composer->render($campaign->template, $values);
            $this->composer->subject($campaign->template, $values);
            hash_update($hash, json_encode($values, JSON_THROW_ON_ERROR));
        }
        $manifest = hash_final($hash);
        $campaign->forceFill([
            'manifest_hash' => $manifest, 'delivery_snapshot' => $header,
            'delivery_snapshot_version' => 3,
            'delivery_snapshot_hash' => $this->authenticator->campaignDigest($campaign->id, $manifest, $header),
            'approved_at' => now(), 'approved_by_user_id' => $actor,
            'provider' => $header['provider'],
        ])->save();
        foreach ($campaign->recipients()->orderBy('id')->lazyById(250) as $recipient) {
            $values = $this->values($recipient);
            $recipient->forceFill([
                'delivery_snapshot' => $values,
                'delivery_snapshot_hash' => $this->authenticator->recipientDigest($campaign->id, $recipient->id, $manifest, $values),
            ])->save();
        }
    }

    public function verify(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        $header = $campaign->delivery_snapshot;
        $values = $recipient->delivery_snapshot;
        if (! is_array($header) || ! is_array($values)
            || ! $this->authenticator->verify((string) $campaign->delivery_snapshot_hash, $this->authenticator->campaignDigest($campaign->id, (string) $campaign->manifest_hash, $header))
            || ! $this->authenticator->verify((string) $recipient->delivery_snapshot_hash, $this->authenticator->recipientDigest($campaign->id, $recipient->id, (string) $campaign->manifest_hash, $values))
            || $values !== $this->values($recipient)
            || $header['purpose'] !== $campaign->purpose || $header['attachment_mode'] !== $campaign->attachment_mode) {
            $this->invalid('Approved campaign or recipient snapshot no longer matches.');
        }

        return $values;
    }

    private function values(EmailCampaignRecipient $recipient): array
    {
        return [
            'id' => $recipient->id, 'email' => $recipient->email, 'name' => $recipient->name,
            'employee_id' => $recipient->employee_id, 'period' => $recipient->period, 'pdf_filename' => $recipient->pdf_filename,
            'consent_status' => $recipient->consent_status,
            'consent_source' => $recipient->consent_source,
            'consent_asserted_at' => $recipient->getRawOriginal('consent_asserted_at'),
            'documents' => [],
        ];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['campaign' => $message]);
    }
}
