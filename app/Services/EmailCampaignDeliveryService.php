<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailOptOut;
use App\Models\EmailTemplate;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailCampaignDeliveryService
{
    public function __construct(private EmailCampaignAttachmentService $attachments, private EmailCampaignPreflightService $preflight, private EmailCampaignDocumentService $generated, private EmailSmtpService $smtp) {}

    public function send(int $campaignId, int $recipientId): void
    {
        $recipient = DB::transaction(function () use ($campaignId, $recipientId) {
            $campaign = EmailCampaign::whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign || ! in_array($campaign->status, ['queued', 'sending'], true)) {
                return null;
            }
            $recipient = $campaign->recipients()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $recipient || $recipient->status !== 'pending') {
                return null;
            }
            $recipient->forceFill(['status' => 'sending', 'claimed_at' => now(), 'error' => null])->save();
            $campaign->update(['status' => 'sending']);

            return $recipient;
        });
        if (! $recipient) {
            return;
        }
        $submitted = false;
        $streams = [];
        $workspace = null;

        try {
            $campaign = EmailCampaign::with('template')->findOrFail($campaignId);
            $snapshot = $campaign->delivery_snapshot;
            if (! $snapshot && ! app()->runningUnitTests() && (! config('email_campaigns.live_enabled') || app()->environment('local', 'testing'))) {
                throw ValidationException::withMessages(['campaign' => 'Live legacy transport is disabled in this environment.']);
            }
            if ($snapshot && ($snapshot['transport']['transport'] ?? null) !== 'array'
                && (! config('email_campaigns.live_enabled') || app()->environment('local', 'testing'))) {
                throw ValidationException::withMessages(['campaign' => 'Live transport is disabled in this environment.']);
            }
            $purpose = $snapshot['purpose'] ?? $campaign->purpose;
            if ($purpose === 'payroll' && (! config('email_campaigns.payroll_enabled') || config('email_campaigns.payroll_password_required'))) {
                throw ValidationException::withMessages(['campaign' => 'Current payroll protection policy blocks sending.']);
            }
            if ($purpose === 'marketing' && EmailOptOut::where('email', strtolower($recipient->email))->exists()) {
                $recipient->update(['status' => 'skipped']);
                $this->totals($campaignId);

                return;
            }
            $values = $recipient->delivery_snapshot ?? ['id' => $recipient->id, ...$recipient->only(['email', 'name', 'employee_id', 'period', 'pdf_filename']), 'documents' => []];
            if ($snapshot) {
                if (! hash_equals((string) $campaign->manifest_hash, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)))) {
                    throw ValidationException::withMessages(['documents' => 'Frozen manifest failed verification.']);
                }
                $frozen = collect($snapshot['recipients'])->firstWhere('id', $recipient->id);
                if ($frozen !== $values || $values['email'] !== $recipient->email || $purpose !== $campaign->purpose || $snapshot['attachment_mode'] !== $campaign->attachment_mode) {
                    throw ValidationException::withMessages(['documents' => 'Recipient manifest failed verification.']);
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
            $metadata = $campaign->documents()->whereIn('id', $values['documents'])->get()->keyBy('id');
            foreach ($values['documents'] as $id) {
                $document = $metadata->get($id);
                $expected = collect($snapshot['documents'])->firstWhere('id', $id);
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
            $recipient->update(['status' => $snapshot ? 'accepted' : 'sent', 'sent_at' => now()]);
        } catch (Throwable) {
            // Transport exception details may contain addresses or credentials. Never log them.
            $recipient->update(['status' => $submitted ? 'uncertain' : 'blocked', 'error' => $submitted ? 'Transport outcome uncertain; reconcile with provider before any resend.' : 'Pre-send verification failed; revalidate the campaign.']);
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
        $this->totals($campaignId);
    }

    public function interrupted(int $campaignId, array $ids): void
    {
        EmailCampaignRecipient::where('email_campaign_id', $campaignId)->whereIn('id', $ids)->where('status', 'sending')
            ->update(['status' => 'uncertain', 'error' => 'Worker interrupted; provider reconciliation required.']);
        $this->totals($campaignId);
    }

    public function totals(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId): void {
            $campaign = EmailCampaign::whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign) {
                return;
            }
            $counts = $campaign->recipients()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $campaign->forceFill([
                'sent_count' => ($counts['accepted'] ?? 0) + ($counts['sent'] ?? 0),
                'failed_count' => ($counts['failed'] ?? 0) + ($counts['blocked'] ?? 0) + ($counts['uncertain'] ?? 0),
                'skipped_count' => $counts['skipped'] ?? 0,
            ]);
            if (! ($counts['pending'] ?? 0) && ! ($counts['sending'] ?? 0) && in_array($campaign->status, ['queued', 'sending', 'needs_attention'], true)) {
                $campaign->status = ($counts['blocked'] ?? 0) || ($counts['uncertain'] ?? 0) ? 'needs_attention' : 'completed';
                $campaign->completed_at = now();
            }
            $campaign->save();
        });
    }
}
