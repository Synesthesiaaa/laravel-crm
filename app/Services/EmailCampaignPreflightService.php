<?php

namespace App\Services;

use App\Models\EmailCampaign;
use Illuminate\Validation\ValidationException;

class EmailCampaignPreflightService
{
    public function __construct(private EmailCampaignAttachmentService $attachments, private EmailSmtpService $smtp, private EmailCampaignSnapshotAuthenticator $authenticator) {}

    public function inspect(EmailCampaign $campaign): array
    {
        $errors = [];
        if ($campaign->purpose === 'payroll') {
            if (! config('email_campaigns.payroll_enabled')) {
                $errors[] = 'Payroll delivery requires approved organizational policy and configuration.';
            }
            if (config('email_campaigns.payroll_password_required')) {
                $errors[] = 'Uploaded PDF password protection is unavailable; payroll policy blocks sending.';
            }
            if (! in_array($campaign->attachment_mode, ['recipient_specific', 'shared_and_recipient_specific'], true)) {
                $errors[] = 'Payroll requires an individual PDF for every recipient.';
            }
        }
        $campaign->load(['template', 'recipients', 'documents']);
        if ($campaign->recipients->count() !== $campaign->recipient_count || ! $campaign->recipient_count) {
            $errors[] = 'Recipient count does not match the campaign.';
        }
        $personal = in_array($campaign->attachment_mode, ['recipient_specific', 'shared_and_recipient_specific'], true);
        $shared = in_array($campaign->attachment_mode, ['shared', 'shared_and_recipient_specific'], true);
        $docRows = [];
        foreach ($campaign->documents as $document) {
            try {
                $this->attachments->bytes($document);
            } catch (ValidationException) {
                $errors[] = 'An intended PDF is missing, expired, modified or unverified.';
            }
            if (($document->email_campaign_recipient_id && ! $personal) || (! $document->email_campaign_recipient_id && ! $shared)) {
                $errors[] = 'Documents conflict with the selected attachment mode.';
            }
            $docRows[] = $document->only(['id', 'email_campaign_id', 'email_campaign_recipient_id', 'filename', 'sha256', 'size']);
        }
        if ($shared && ! $campaign->documents->whereNull('email_campaign_recipient_id')->count()) {
            $errors[] = 'Shared PDFs are missing.';
        }
        $rows = [];
        foreach ($campaign->recipients as $recipient) {
            $own = $campaign->documents->where('email_campaign_recipient_id', $recipient->id);
            if ($personal && ($own->count() !== 1 || $own->first()?->filename !== $recipient->pdf_filename)) {
                $errors[] = 'Every recipient requires exactly one matching personal PDF.';
            }
            if ($campaign->purpose === 'payroll' && (! $recipient->employee_id || ! $recipient->period)) {
                $errors[] = 'Payroll identifiers and periods are required.';
            }
            $values = $recipient->only(['email', 'name', 'employee_id', 'period', 'pdf_filename']);

            try {
                $this->personalize($campaign->template->subject, $values);
                $this->personalize($campaign->template->html_body, $values);
                if ($campaign->template->pdf_enabled) {
                    $this->personalize((string) $campaign->template->pdf_body, $values);
                }
            } catch (ValidationException) {
                $errors[] = 'Unknown or missing template variables; review recipient data.';
            }
            $rows[] = ['id' => $recipient->id, ...$values, 'documents' => $campaign->documents->filter(fn ($d) => ! $d->email_campaign_recipient_id || $d->email_campaign_recipient_id === $recipient->id)->pluck('id')->all()];
        }
        [$address, $name] = $this->smtp->sender();
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Sender address is invalid.';
        }
        $settings = $this->smtp->activeSettings();
        $snapshot = [
            'purpose' => $campaign->purpose, 'attachment_mode' => $campaign->attachment_mode,
            'template' => $campaign->template->only(['name', 'subject', 'html_body', 'pdf_enabled', 'pdf_body', 'pdf_password']),
            'sender' => [$address, $name],
            'transport' => $settings ? $this->smtp->transportConfiguration($settings) : config('mail.mailers.'.config('mail.default')),
            'documents' => $docRows, 'recipients' => $rows,
        ];

        return ['errors' => array_values(array_unique($errors)), 'hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'snapshot' => $snapshot];
    }

    public function freeze(EmailCampaign $campaign, int $actor): void
    {
        $result = $this->inspect($campaign);
        if ($result['errors']) {
            throw ValidationException::withMessages(['campaign' => $result['errors']]);
        }
        if ($campaign->needsReview() && ! hash_equals($result['hash'], (string) $campaign->dry_run_hash)) {
            throw ValidationException::withMessages(['campaign' => 'Run the internal dry-run for the current mapping before confirming.']);
        }
        $manifest = $result['snapshot'];
        $header = [
            'purpose' => $manifest['purpose'],
            'attachment_mode' => $manifest['attachment_mode'],
            'template' => $manifest['template'],
            'sender' => $manifest['sender'],
            'transport' => $manifest['transport'],
            'shared_documents' => array_values(array_filter(
                $manifest['documents'],
                static fn (array $document): bool => $document['email_campaign_recipient_id'] === null,
            )),
        ];
        $campaign->forceFill([
            'delivery_snapshot' => $header,
            'delivery_snapshot_version' => 2,
            'delivery_snapshot_hash' => $this->authenticator->campaignDigest($campaign->id, $result['hash'], $header),
            'manifest_hash' => $result['hash'],
            'approved_at' => now(),
            'approved_by_user_id' => $actor,
        ])->save();

        $documentsById = collect($manifest['documents'])->keyBy('id');
        foreach ($campaign->recipients as $index => $recipient) {
            $values = $manifest['recipients'][$index];
            $recipientDocuments = array_values(array_filter(array_map(
                static fn (int $id): ?array => ($document = $documentsById->get($id))
                    && $document['email_campaign_recipient_id'] === $recipient->id ? $document : null,
                $values['documents'],
            )));
            $values['documents'] = $recipientDocuments;
            $recipient->forceFill([
                'delivery_snapshot' => $values,
                'delivery_snapshot_hash' => $this->authenticator->recipientDigest($campaign->id, $recipient->id, $result['hash'], $values),
            ])->save();
        }
    }

    public function personalize(string $text, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([^{}]+)\s*\}\}/', function ($match) use ($values): string {
            $key = trim($match[1]);
            if (! in_array($key, ['name', 'email', 'employee_id', 'period'], true)) {
                throw ValidationException::withMessages(['campaign' => 'Unsupported template variable.']);
            }
            $value = $values[$key] ?? null;
            if (blank($value) && $key !== 'name') {
                throw ValidationException::withMessages(['campaign' => 'Missing template variable.']);
            }

            return (string) ($value ?: 'Recipient');
        }, $text);
    }
}
