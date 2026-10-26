<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailOptOut;
use App\Models\EmailTemplate;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailCampaignDeliveryService
{
    public function __construct(
        private EmailCampaignAttachmentService $attachments,
        private EmailCampaignPreflightService $preflight,
        private EmailCampaignDocumentService $generated,
        private EmailSmtpService $smtp,
        private EmailCampaignSendingPolicy $sendingPolicy,
        private EmailCampaignRecipientTransitionService $transitions,
        private EmailCampaignSnapshotAuthenticator $authenticator,
    ) {}

    public function send(int $campaignId, int $recipientId): void
    {
        $recipient = $this->transitions->claim($campaignId, $recipientId);
        if (! $recipient) {
            return;
        }
        $submitted = false;
        $streams = [];
        $workspace = null;

        try {
            $campaign = EmailCampaign::with('template')->findOrFail($campaignId);
            $snapshot = $campaign->delivery_snapshot;
            if (! $snapshot && ! app()->runningUnitTests() && ! $this->sendingPolicy->allowsLive()) {
                throw ValidationException::withMessages(['campaign' => 'Live legacy transport is disabled in this environment.']);
            }
            if ($snapshot && ($snapshot['transport']['transport'] ?? null) !== 'array'
                && ! $this->sendingPolicy->allowsLive()) {
                throw ValidationException::withMessages(['campaign' => 'Live transport is disabled in this environment.']);
            }
            $purpose = $snapshot['purpose'] ?? $campaign->purpose;
            if ($purpose === 'payroll' && (! config('email_campaigns.payroll_enabled') || config('email_campaigns.payroll_password_required'))) {
                throw ValidationException::withMessages(['campaign' => 'Current payroll protection policy blocks sending.']);
            }
            if ($purpose === 'marketing' && EmailOptOut::where('email', strtolower($recipient->email))->exists()) {
                $this->transitions->transition($campaignId, $recipientId, 'sending', 'skipped');

                return;
            }
            $values = $recipient->delivery_snapshot ?? ['id' => $recipient->id, ...$recipient->only(['email', 'name', 'employee_id', 'period', 'pdf_filename']), 'documents' => []];
            if ($snapshot) {
                if ((int) $campaign->delivery_snapshot_version === 2) {
                    $headerValid = $this->authenticator->verify(
                        (string) $campaign->delivery_snapshot_hash,
                        $this->authenticator->campaignDigest($campaign->id, (string) $campaign->manifest_hash, $snapshot),
                    );
                    $recipientValid = $this->authenticator->verify(
                        (string) $recipient->delivery_snapshot_hash,
                        $this->authenticator->recipientDigest($campaign->id, $recipient->id, (string) $campaign->manifest_hash, $values),
                    );
                    $currentRecipient = [
                        'id' => $recipient->id,
                        'email' => $recipient->email,
                        'name' => $recipient->name,
                        'employee_id' => $recipient->employee_id,
                        'period' => $recipient->period,
                        'pdf_filename' => $recipient->pdf_filename,
                    ];
                    $frozenRecipient = collect($values)->except('documents')->all();
                    if (! $headerValid || ! $recipientValid || $frozenRecipient !== $currentRecipient
                        || $purpose !== $campaign->purpose || $snapshot['attachment_mode'] !== $campaign->attachment_mode) {
                        throw ValidationException::withMessages(['documents' => 'Recipient snapshot failed authentication.']);
                    }
                } else {
                    if ((int) $campaign->delivery_snapshot_version !== 1
                        || ! hash_equals((string) $campaign->manifest_hash, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)))) {
                        throw ValidationException::withMessages(['documents' => 'Frozen manifest failed verification.']);
                    }
                    $frozen = collect($snapshot['recipients'])->firstWhere('id', $recipient->id);
                    if ($frozen !== $values || $values['email'] !== $recipient->email || $purpose !== $campaign->purpose || $snapshot['attachment_mode'] !== $campaign->attachment_mode) {
                        throw ValidationException::withMessages(['documents' => 'Recipient manifest failed verification.']);
                    }
                }
            } elseif ($campaign->needsReview()) {
                throw ValidationException::withMessages(['documents' => 'Required immutable manifest is missing.']);
            }
            $template = $snapshot ? new EmailTemplate($snapshot['template']) : $campaign->template;
            $subject = $this->preflight->personalize($template->subject, $values);
            $html = '<div>'.nl2br(e($this->preflight->personalize($template->html_body, $values))).'</div>';
            $unsubscribe = $purpose === 'marketing' ? URL::signedRoute('email-campaigns.unsubscribe', ['recipient' => $recipient->id]) : null;
            if ($unsubscribe) {
                $html .= '<p style="font-size:12px">You are receiving this message because you subscribed to updates. <a href="'.e($unsubscribe).'">Unsubscribe</a></p>';
            }
            $loaded = [];
            if ((int) $campaign->delivery_snapshot_version === 2 && $snapshot) {
                $expectedDocuments = [...($snapshot['shared_documents'] ?? []), ...($values['documents'] ?? [])];
            } else {
                $expectedDocuments = collect($snapshot['documents'] ?? [])
                    ->whereIn('id', $values['documents'] ?? [])
                    ->values()->all();
            }
            $documentIds = array_map(static fn (array $document): int => (int) $document['id'], $expectedDocuments);
            $metadata = $campaign->documents()->whereIn('id', $documentIds)->get()->keyBy('id');
            foreach ($expectedDocuments as $expected) {
                $id = (int) $expected['id'];
                $document = $metadata->get($id);
                if (! $document || $document->only(['id', 'email_campaign_id', 'email_campaign_recipient_id', 'filename', 'sha256', 'size']) !== $expected
                    || ($document->email_campaign_recipient_id && $document->email_campaign_recipient_id !== $recipient->id)) {
                    throw ValidationException::withMessages(['documents' => 'Attachment ownership failed verification.']);
                }
                if (! $workspace) {
                    $workspace = storage_path('app/private/email-imports/'.\Illuminate\Support\Str::uuid());
                    if (! mkdir($workspace, 0700, true)) {
                        throw new \RuntimeException('Cannot prepare private attachment workspace.');
                    }
                }
                $path = $workspace.'/'.\Illuminate\Support\Str::uuid().'.pdf';
                $stream = fopen($path, 'x+b');
                if (! $stream) {
                    throw new \RuntimeException('Cannot prepare private attachment stream.');
                }
                chmod($path, 0600);
                $streams[] = $stream;
                $bytes = $this->attachments->bytes($document);
                if (fwrite($stream, $bytes) !== strlen($bytes)) {
                    throw new \RuntimeException('Cannot write verified attachment stream.');
                }
                unset($bytes);
                rewind($stream);
                $loaded[] = [$document->filename, $stream];
            }
            // All attachments verified before entering the transport boundary.
            if ($template->pdf_enabled) {
                $template->pdf_body = $this->preflight->personalize((string) $template->pdf_body, $values);
                $loaded[] = ['generated-template.pdf', $this->generated->renderPdf($template, $values['name'] ?: 'Recipient', $values['email'])];
            }
            [$fromAddress, $fromName] = $snapshot['sender'] ?? $this->smtp->sender();
            $mailer = $snapshot ? Mail::build($snapshot['transport']) : $this->smtp->mailer();
            $submitted = true;
            $mailer->html($html, function (Message $message) use ($values, $subject, $unsubscribe, $loaded, $fromAddress, $fromName): void {
                $message->from($fromAddress, $fromName)->to($values['email'], $values['name'] ?: null)->subject($subject);
                if ($unsubscribe) {
                    $message->getSymfonyMessage()->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                }
                foreach ($loaded as [$filename, $bytes]) {
                    $message->attachData($bytes, $filename, ['mime' => 'application/pdf']);
                }
            });
            $this->transitions->transition($campaignId, $recipientId, 'sending', $snapshot ? 'accepted' : 'sent', ['sent_at' => now()]);
        } catch (Throwable) {
            // Transport exception details may contain addresses or credentials. Never log them.
            $this->transitions->transition(
                $campaignId,
                $recipientId,
                'sending',
                $submitted ? 'uncertain' : 'blocked',
                ['error' => $submitted ? 'Transport outcome uncertain; reconcile with provider before any resend.' : 'Pre-send verification failed; revalidate the campaign.'],
            );
        } finally {
            foreach ($streams as $stream) {
                fclose($stream);
            }
            if ($workspace) {
                foreach (glob($workspace.'/*') ?: [] as $path) {
                    unlink($path);
                }
                rmdir($workspace);
            }
        }
    }

    public function interrupted(int $campaignId, array $ids): void
    {
        $this->transitions->transitionMany($campaignId, $ids, 'sending', 'uncertain', ['error' => 'Worker interrupted; provider reconciliation required.']);
    }

    public function totals(int $campaignId): void
    {
        $this->transitions->reconcile($campaignId);
    }
}
