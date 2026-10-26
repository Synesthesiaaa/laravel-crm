<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaignBatch;
use App\Jobs\SendEmailCampaignRecipient;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDocument;
use App\Models\EmailOptOut;
use App\Models\EmailTemplate;
use App\Services\EmailCampaignAttachmentService;
use App\Services\EmailCampaignDocumentService;
use App\Services\EmailCampaignPreflightService;
use App\Services\EmailCampaignQueueStatus;
use App\Services\EmailRecipientCsvImporter;
use App\Services\EmailSmtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailCampaignsController extends Controller
{
    public function index(EmailCampaignQueueStatus $queueStatus): View
    {
        return view('admin.email-campaigns.index', [
            'campaigns' => EmailCampaign::with('template')->latest()->paginate(15),
            'templates' => EmailTemplate::latest()->get(),
            'queueStatus' => $queueStatus->inspect(),
            'stalledCampaigns' => EmailCampaign::query()
                ->whereIn('status', ['queued', 'sending'])
                ->where('started_at', '<=', now()->subMinutes(2))
                ->where('sent_count', 0)
                ->where('failed_count', 0)
                ->where('skipped_count', 0)
                ->count(),
        ]);
    }

    public function downloadRecipientTemplate(): StreamedResponse
    {
        return response()->streamDownload(static function (): void {
            $output = fopen('php://output', 'wb');

            fputcsv($output, ['email', 'name']);
            fputcsv($output, ['jane.doe@example.com', 'Jane Doe']);
            fputcsv($output, ['john.smith@example.com', 'John Smith']);

            fclose($output);
        }, 'email-recipients-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $this->templateData($request);
        $data['pdf_enabled'] = $request->boolean('pdf_enabled');
        $data['created_by_user_id'] = $request->user()->id;
        if (! $data['pdf_enabled']) {
            $data['pdf_password'] = null;
            $data['pdf_body'] = null;
        }

        EmailTemplate::create($data);

        return back()->with('success', 'Email template saved.');
    }

    public function updateTemplate(Request $request, EmailTemplate $template): RedirectResponse
    {
        if ($template->campaigns()->whereIn('status', ['queued', 'sending', 'completed'])->exists()) {
            throw ValidationException::withMessages(['template' => 'This template has already been used in a sent campaign. Create a new template for changes.']);
        }

        $data = $this->templateData($request, $template);
        $data['pdf_enabled'] = $request->boolean('pdf_enabled');
        if (! $data['pdf_enabled']) {
            $data['pdf_body'] = null;
            $data['pdf_password'] = null;
        } elseif (blank($data['pdf_password'] ?? null)) {
            unset($data['pdf_password']); // Keep the existing encrypted password.
        }
        $template->update($data);

        return back()->with('success', 'Template updated.');
    }

    public function import(Request $request, EmailCampaignDocumentService $documents): RedirectResponse
    {
        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);
        $text = $documents->extractText($data['document']);
        $firstLine = trim(strtok($text, "\n") ?: '');
        $subject = mb_substr($firstLine ?: 'Imported document', 0, 255);
        $template = EmailTemplate::create([
            'created_by_user_id' => $request->user()->id,
            'name' => 'Document import '.now()->format('Y-m-d H:i'),
            'subject' => $subject,
            'html_body' => $text,
            'pdf_body' => $text,
            'pdf_enabled' => false,
        ]);

        return back()->with('success', "Document text imported into template #{$template->id}. Review it before sending.");
    }

    public function preview(EmailTemplate $template, EmailCampaignDocumentService $documents)
    {
        abort_unless($template->pdf_enabled, 404);

        return response()->streamDownload(
            fn () => print ($documents->renderPdf($template, 'Sample Recipient', 'sample@example.com')),
            'template-preview-'.$template->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function store(\App\Http\Requests\StoreEmailCampaignRequest $request, EmailRecipientCsvImporter $importer, EmailCampaignAttachmentService $attachments): RedirectResponse
    {
        $data = $request->validated();

        $purpose = $data['purpose'] ?? 'marketing';
        $mode = $data['attachment_mode'] ?? 'none';
        $personal = in_array($mode, ['recipient_specific', 'shared_and_recipient_specific'], true);
        if ($purpose === 'payroll' && ! $personal) {
            throw ValidationException::withMessages(['attachment_mode' => 'Payroll requires recipient-specific PDFs.']);
        }
        if ((! $personal && ($request->hasFile('document_zip') || $request->hasFile('personal_pdfs')))
            || (! in_array($mode, ['shared', 'shared_and_recipient_specific'], true) && $request->hasFile('shared_pdfs'))) {
            throw ValidationException::withMessages(['documents' => 'Uploads conflict with the selected attachment mode.']);
        }
        $strict = $personal || $purpose !== 'marketing';
        $rows = $strict ? $importer->read($data['recipient_csv']->getRealPath(), $personal, $purpose === 'payroll') : $this->readRecipients($data['recipient_csv']->getRealPath());
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

        $campaign = DB::transaction(function () use ($data, $rows, $request, $attachments, $purpose, $mode): EmailCampaign {
            $campaign = EmailCampaign::create([
                'name' => $data['name'],
                'email_template_id' => $data['email_template_id'],
                'created_by_user_id' => $request->user()->id,
                'batch_size' => (int) $data['batch_size'],
                'delay_seconds' => (int) $data['delay_seconds'],
                'recipient_count' => count($rows),
                'open_count' => count($rows),
                'attention_count' => 0,
                'status' => 'draft',
                'purpose' => $purpose,
                'attachment_mode' => $mode,
            ]);

            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                $now = now();
                $campaign->recipients()->insert(array_map(static fn (array $recipient): array => [
                    ...$recipient,
                    'email_campaign_id' => $campaign->id,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            $attachments->ingest($campaign, $request->file('personal_pdfs', []), $request->file('document_zip'), $request->file('shared_pdfs', []), $request->user()->id);

            return $campaign;
        });

        \Illuminate\Support\Facades\Log::info('email_campaign.upload', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id, 'recipients' => $campaign->recipient_count]);

        return $campaign->needsReview() ? redirect()->route('admin.email-campaigns.review', $campaign)->with('success', 'Draft created. Review every recipient and intended PDF before sending.') : back()->with('success', sprintf(
            'Campaign ready with %d recipients. %d opted-out addresses were excluded. Review it and select Start sending.',
            $campaign->recipient_count,
            count($blocked),
        ));
    }

    public function send(Request $request, EmailCampaign $campaign, EmailSmtpService $smtp, EmailCampaignQueueStatus $queueStatus, EmailCampaignPreflightService $preflight, \App\Services\EmailCampaignSendingPolicy $sendingPolicy): RedirectResponse
    {
        if (! app()->runningUnitTests() && ! $sendingPolicy->allowsLive()) {
            throw ValidationException::withMessages(['campaign' => 'Live campaign sending is disabled. Enable EMAIL_CAMPAIGN_LIVE_ENABLED and, on local, EMAIL_CAMPAIGN_ALLOW_LOCAL before queueing.']);
        }
        if (! app()->runningUnitTests() && config('queue.connections.'.config('email_campaigns.connection').'.driver') === 'sync') {
            throw ValidationException::withMessages(['campaign' => 'Configure a database or Redis queue before sending a campaign.']);
        }
        if (! app()->runningUnitTests() && ! $smtp->isReady()) {
            throw ValidationException::withMessages(['campaign' => 'Configure SMTP under Super Admin → Email Configuration before sending a campaign.']);
        }
        if (! app()->runningUnitTests() && ($problem = $queueStatus->inspect()['problem'])) {
            throw ValidationException::withMessages(['campaign' => $problem.' Verify the email-campaigns queue worker before sending.']);
        }

        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);

        if ($campaign->needsReview()) {
            $request->validate(['confirmed_mapping' => ['accepted'], 'confirmed_live_send' => ['accepted']]);
        }
        DB::transaction(function () use ($campaign, $preflight, $request): void {
            $campaign = EmailCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($campaign->status !== 'draft') {
                throw ValidationException::withMessages(['campaign' => 'This campaign has already been started or cancelled.']);
            }

            $preflight->freeze($campaign, $request->user()->id);
            $campaign->update(['status' => 'queued', 'started_at' => now()]);
            if ($campaign->needsReview()) {
                foreach ($campaign->recipients as $index => $recipient) {
                    SendEmailCampaignRecipient::dispatch($campaign->id, $recipient->id)
                        ->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))
                        ->delay(now()->addSeconds((int) floor($index / $campaign->batch_size) * $campaign->delay_seconds))->afterCommit();
                }

                return;
            }
            $batches = (int) ceil($campaign->recipient_count / $campaign->batch_size);

            for ($index = 0; $index < $batches; $index++) {
                SendEmailCampaignBatch::dispatch($campaign->id, $index * $campaign->batch_size, $campaign->batch_size)
                    ->onConnection(config('email_campaigns.connection'))
                    ->onQueue(config('email_campaigns.queue'))
                    ->delay(now()->addSeconds($index * $campaign->delay_seconds))
                    ->afterCommit();
            }
        });

        \Illuminate\Support\Facades\Log::info('email_campaign.start', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id]);

        return back()->with('success', 'Campaign queued for batch delivery.');
    }

    public function cancel(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);
        DB::transaction(function () use ($campaign): void {
            $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['draft', 'queued', 'sending'], true)) {
                $locked->update(['status' => 'cancelled', 'completed_at' => now()]);
                if ($locked->delivery_snapshot) {
                    app(\App\Services\EmailCampaignRecipientTransitionService::class)->transitionAll($locked->id, 'pending', 'cancelled');
                }
            }
        });
        app(\App\Services\EmailCampaignDeliveryService::class)->totals($campaign->id);
        \Illuminate\Support\Facades\Log::info('email_campaign.cancel', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id]);

        return back()->with('success', 'Campaign cancelled. Any message already in transit may still finish.');
    }

    public function downloadPersonalizedTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['email', 'name', 'employee_id', 'period', 'pdf_filename'], escape: '');
            fputcsv($out, ['alice@example.com', 'Alice Example', 'EMP-1001', '2026-09', 'EMP-1001-2026-09.pdf'], escape: '');
            fclose($out);
        }, 'personalized-pdf-recipients.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function updateDraft(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $campaign);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email_template_id' => ['required', 'exists:email_templates,id'], 'batch_size' => ['required', 'integer', 'between:1,100'], 'delay_seconds' => ['required', 'integer', 'between:1,3600']]);
        DB::transaction(function () use ($campaign, $data): void {
            $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409);
            $locked->fill($data)->forceFill(['dry_run_hash' => null])->save();
        });

        return back()->with('success', 'Draft updated. Repeat the internal dry-run before confirming.');
    }

    public function testSelf(Request $request, EmailCampaign $campaign, EmailSmtpService $smtp): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);
        if (! $smtp->isReady()) {
            return back()->withErrors(['smtp' => 'Configure and enable SMTP in Email Configuration before sending a synthetic test.'], 'syntheticTest');
        }
        if (! filter_var($request->user()->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['smtp' => 'Your account needs a valid email address to receive the synthetic test.'], 'syntheticTest');
        }

        try {
            [$address, $name] = $smtp->sender();
            $smtp->mailer()->html('<p>Synthetic CRM email test. This contains no campaign recipients or personal documents.</p>', function (\Illuminate\Mail\Message $message) use ($request, $address, $name): void {
                $message->from($address, $name)->to($request->user()->email)->subject('Synthetic CRM document delivery test');
            });
        } catch (\Throwable) {
            return back()->withErrors(['smtp' => 'The synthetic test could not be confirmed. Check the SMTP host, port, security, credentials and provider connectivity in Email Configuration.'], 'syntheticTest');
        }

        return back()->with('success', 'Synthetic test accepted by the transport for your account address. No personal PDFs were attached.');
    }

    public function review(Request $request, EmailCampaign $campaign, EmailCampaignPreflightService $preflight): View
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);
        $result = $campaign->status === 'draft' ? $preflight->inspect($campaign) : ['errors' => [], 'hash' => $campaign->manifest_hash];
        $counts = $campaign->recipients()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        \Illuminate\Support\Facades\Log::info('email_campaign.review', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id]);

        return view('admin.email-campaigns.review', [
            'campaign' => $campaign, 'preflight' => $result, 'counts' => $counts,
            'recipients' => $campaign->recipients()->orderBy('id')->paginate(50),
            'documents' => $campaign->documents()->get(),
            'sender' => $campaign->delivery_snapshot['sender'][0] ?? app(EmailSmtpService::class)->sender()[0],
        ]);
    }

    public function document(Request $request, EmailCampaign $campaign, EmailCampaignDocument $document, EmailCampaignAttachmentService $attachments)
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);
        abort_unless($document->email_campaign_id === $campaign->id, 404);
        $bytes = $attachments->bytes($document);
        \Illuminate\Support\Facades\Log::info('email_campaign.preview', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id, 'document_id' => $document->id]);

        return response()->streamDownload(fn () => print ($bytes), $document->filename, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function dryRun(Request $request, EmailCampaign $campaign, EmailCampaignPreflightService $preflight): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $campaign);
        DB::transaction(function () use ($campaign, $preflight): void {
            $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409);
            $result = $preflight->inspect($locked);
            if ($result['errors']) {
                throw ValidationException::withMessages(['campaign' => $result['errors']]);
            }
            $locked->forceFill(['dry_run_hash' => $result['hash']])->save();
        });
        \Illuminate\Support\Facades\Log::info('email_campaign.dry_run', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id]);

        return back()->with('success', 'Internal dry-run passed: mappings, content and document integrity verified. No email was sent.');
    }

    public function report(EmailCampaign $campaign): StreamedResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);

        return response()->streamDownload(function () use ($campaign): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['recipient_id', 'status', 'provider_accepted_at'], escape: '');
            foreach ($campaign->recipients()->orderBy('id')->cursor() as $recipient) {
                fputcsv($out, [$recipient->id, in_array($recipient->status, ['pending', 'sending', 'sent', 'accepted', 'skipped', 'failed', 'blocked', 'uncertain', 'cancelled'], true) ? $recipient->status : 'unknown', $recipient->sent_at?->toIso8601String()], escape: '');
            }
            fclose($out);
        }, 'email-status-'.$campaign->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function retry(Request $request, EmailCampaign $campaign, EmailCampaignPreflightService $preflight, \App\Services\EmailCampaignSendingPolicy $sendingPolicy): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);
        if (! app()->runningUnitTests() && (! $sendingPolicy->allowsLive() || app(EmailCampaignQueueStatus::class)->inspect()['problem'])) {
            throw ValidationException::withMessages(['campaign' => 'Live sending and a healthy dedicated worker are required before retry.']);
        }
        $request->validate(['confirmed_retry' => ['accepted']]);
        DB::transaction(function () use ($campaign, $preflight): void {
            $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'needs_attention' || ! $locked->delivery_snapshot) {
                throw ValidationException::withMessages(['campaign' => 'Only verified pre-send failures can be retried.']);
            }
            $result = $preflight->inspect($locked);
            if ($result['errors'] || ! hash_equals($locked->manifest_hash, $result['hash'])) {
                throw ValidationException::withMessages(['campaign' => 'Restore the approved documents and frozen settings before retrying.']);
            }
            $ids = $locked->recipients()->where('status', 'blocked')->pluck('id');
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['campaign' => 'Uncertain submissions require provider reconciliation and cannot be retried here.']);
            }
            app(\App\Services\EmailCampaignRecipientTransitionService::class)->transitionMany($locked->id, $ids->all(), 'blocked', 'pending', ['error' => null]);
            $locked->update(['status' => 'queued', 'completed_at' => null]);
            foreach ($ids as $index => $id) {
                SendEmailCampaignRecipient::dispatch($locked->id, $id)->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))
                    ->delay(now()->addSeconds($index * $locked->delay_seconds))->afterCommit();
            }
        });
        \Illuminate\Support\Facades\Log::info('email_campaign.retry', ['actor_id' => $request->user()->id, 'campaign_id' => $campaign->id]);

        return back()->with('success', 'Verified pre-send failures queued. Uncertain outcomes remain excluded.');
    }

    public function reconcile(Request $request, EmailCampaign $campaign, \App\Models\EmailCampaignRecipient $recipient): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $campaign);
        abort_unless($recipient->email_campaign_id === $campaign->id, 404);
        $data = $request->validate([
            'outcome' => ['required', 'in:accepted,not_submitted'],
            'provider_case' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'accepted_at' => ['required_if:outcome,accepted', 'nullable', 'date', 'before_or_equal:now'],
            'confirmed_evidence' => ['accepted'],
        ]);
        DB::transaction(function () use ($campaign, $recipient, $data, $request): void {
            $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $row = $locked->recipients()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            if ($row->status !== 'uncertain') {
                throw ValidationException::withMessages(['campaign' => 'Only uncertain outcomes can be reconciled.']);
            }
            app(\App\Services\EmailCampaignRecipientTransitionService::class)->transition(
                $locked->id,
                $row->id,
                'uncertain',
                $data['outcome'] === 'accepted' ? 'accepted' : 'blocked',
                [
                    'sent_at' => $data['outcome'] === 'accepted' ? $data['accepted_at'] : null,
                    'error' => $data['outcome'] === 'accepted' ? null : 'Operator recorded provider confirmation of no submission. Revalidation and explicit retry required.',
                ],
            );
            activity('email-campaign')->causedBy($request->user())->event('reconciliation')
                ->withProperties(['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id, 'outcome' => $data['outcome'], 'provider_case' => $data['provider_case']])
                ->log('email_campaign.reconciliation');
        });

        return back()->with('success', 'Provider evidence recorded. No message was sent by reconciliation.');
    }

    /** @return array<string, array{email: string, name: string|null}> */
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

                $recipients[$email] = [
                    'email' => $email,
                    'name' => $nameIndex !== false ? mb_substr(trim((string) ($row[$nameIndex] ?? '')), 0, 255) : null,
                ];
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

    private function templateData(Request $request, ?EmailTemplate $template = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'html_body' => ['required', 'string', 'max:50000'],
            'pdf_enabled' => ['sometimes', 'boolean'],
            'pdf_body' => ['required_if:pdf_enabled,1', 'nullable', 'string', 'max:50000'],
            'pdf_password' => ['nullable', 'string', 'min:8', 'max:128'],
        ];
        if ($request->boolean('pdf_enabled') && ! $template?->pdf_password) {
            $rules['pdf_password'][] = 'required';
        }

        return $request->validate($rules);
    }
}
