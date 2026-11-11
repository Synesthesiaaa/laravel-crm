<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailOptOut;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailCampaignDraftService
{
    public function __construct(private EmailRecipientCsvImporter $importer, private EmailCampaignAttachmentService $attachments) {}

    /** Shared draft creation; this never approves or queues any recipient delivery. */
    public function create(User $actor, array $data, UploadedFile $csv, array $personalFiles = [], ?UploadedFile $zip = null, array $sharedFiles = []): array
    {
        abort_unless($actor->isSuperAdmin(), 403);
        $purpose = $data['purpose'] ?? 'marketing';
        $mode = $data['attachment_mode'] ?? 'none';
        $personal = in_array($mode, ['recipient_specific', 'shared_and_recipient_specific'], true);
        if ($purpose === 'payroll' && ! $personal) {
            throw ValidationException::withMessages(['attachment_mode' => 'Payroll requires recipient-specific PDFs.']);
        }
        if ((! $personal && ($zip || $personalFiles)) || (! in_array($mode, ['shared', 'shared_and_recipient_specific'], true) && $sharedFiles)) {
            throw ValidationException::withMessages(['documents' => 'Uploads conflict with the selected attachment mode.']);
        }
        $strict = $personal || $purpose !== 'marketing';
        $rows = $strict ? $this->importer->read($csv->getRealPath(), $personal, $purpose === 'payroll') : $this->readRecipients($csv->getRealPath());
        $blocked = $purpose === 'marketing' ? EmailOptOut::query()->whereIn('email', array_map(fn ($r) => strtolower($r['email']), $rows))->pluck('email')->all() : [];
        if ($personal && $blocked) {
            throw ValidationException::withMessages(['recipient_csv' => 'The personal manifest contains opted-out marketing recipients. Correct the manifest before import.']);
        }
        foreach ($blocked as $email) {
            unset($rows[$email]);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['recipient_csv' => 'The list has no eligible email addresses.']);
        }
        $campaign = DB::transaction(function () use ($actor, $data, $rows, $purpose, $mode, $personalFiles, $zip, $sharedFiles): EmailCampaign {
            $campaign = EmailCampaign::create([
                'name' => $data['name'], 'email_template_id' => $data['email_template_id'],
                'created_by_user_id' => $actor->id, 'batch_size' => (int) $data['batch_size'],
                'delay_seconds' => (int) $data['delay_seconds'], 'recipient_count' => count($rows),
                'open_count' => count($rows), 'attention_count' => 0, 'status' => 'draft',
                'purpose' => $purpose, 'attachment_mode' => $mode,
            ]);
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                $now = now();
                $campaign->recipients()->insert(array_map(static fn (array $recipient): array => [
                    ...$recipient, 'email_campaign_id' => $campaign->id, 'status' => 'pending',
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
            $this->attachments->ingest($campaign, $personalFiles, $zip, $sharedFiles, $actor->id);

            return $campaign;
        });
        \Illuminate\Support\Facades\Log::info('email_campaign.upload', ['actor_id' => $actor->id, 'campaign_id' => $campaign->id, 'recipients' => $campaign->recipient_count]);

        return ['campaign' => $campaign, 'excluded' => count($blocked)];
    }

    private function readRecipients(string $path): array
    {
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw ValidationException::withMessages(['recipient_csv' => 'Could not read CSV file.']);
        }

        try {
            $header = fgetcsv($file);
            $header = array_map(static fn ($value) => strtolower(trim((string) $value, "\xEF\xBB\xBF \t\r\n")), $header ?: []);
            $emailIndex = array_search('email', $header, true);
            $nameIndex = array_search('name', $header, true);
            if ($emailIndex === false) {
                throw ValidationException::withMessages(['recipient_csv' => 'CSV must contain an email column (and optional name column).']);
            }
            $recipients = [];
            $line = 1;
            while (($row = fgetcsv($file)) !== false) {
                $line++;
                $email = strtolower(trim((string) ($row[$emailIndex] ?? '')));
                if ($email === '' && count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                    throw ValidationException::withMessages(['recipient_csv' => "Invalid email at CSV row {$line}."]);
                }
                $recipients[$email] = ['email' => $email, 'name' => $nameIndex !== false ? mb_substr(trim((string) ($row[$nameIndex] ?? '')), 0, 255) : null];
                if (count($recipients) > 20000 || $line > 50000) {
                    throw ValidationException::withMessages(['recipient_csv' => 'A campaign supports up to 20,000 unique recipients.']);
                }
            }
            if ($recipients === []) {
                throw ValidationException::withMessages(['recipient_csv' => 'CSV has no recipients.']);
            }

            return $recipients;
        } finally {
            fclose($file);
        }
    }
}
