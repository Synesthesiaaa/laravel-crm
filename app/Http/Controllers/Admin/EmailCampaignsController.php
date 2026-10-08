<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaignBatch;
use App\Models\EmailCampaign;
use App\Models\EmailOptOut;
use App\Models\EmailTemplate;
use App\Services\EmailCampaignDocumentService;
use App\Services\EmailSmtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailCampaignsController extends Controller
{
    public function index(): View
    {
        return view('admin.email-campaigns.index', [
            'campaigns' => EmailCampaign::with('template')->latest()->paginate(15),
            'templates' => EmailTemplate::latest()->get(),
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email_template_id' => ['required', 'integer', 'exists:email_templates,id'],
            'recipient_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'batch_size' => ['required', 'integer', 'between:1,100'],
            'delay_seconds' => ['required', 'integer', 'between:1,3600'],
            'confirmed_permission' => ['accepted'],
        ]);

        $rows = $this->readRecipients($data['recipient_csv']->getRealPath());
        $blocked = EmailOptOut::query()->whereIn('email', array_keys($rows))->pluck('email')->all();
        foreach ($blocked as $email) {
            unset($rows[$email]);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['recipient_csv' => 'The list has no eligible email addresses.']);
        }

        $campaign = DB::transaction(function () use ($data, $rows, $request): EmailCampaign {
            $campaign = EmailCampaign::create([
                'name' => $data['name'],
                'email_template_id' => $data['email_template_id'],
                'created_by_user_id' => $request->user()->id,
                'batch_size' => (int) $data['batch_size'],
                'delay_seconds' => (int) $data['delay_seconds'],
                'recipient_count' => count($rows),
                'status' => 'draft',
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

            return $campaign;
        });

        return back()->with('success', sprintf(
            'Campaign ready with %d recipients. %d opted-out addresses were excluded. Review it and select Start sending.',
            $campaign->recipient_count,
            count($blocked),
        ));
    }

    public function send(EmailCampaign $campaign, EmailSmtpService $smtp): RedirectResponse
    {
        if (! app()->runningUnitTests() && config('queue.default') === 'sync') {
            throw ValidationException::withMessages(['campaign' => 'Configure a database or Redis queue before sending a campaign.']);
        }
        if (! app()->runningUnitTests() && ! $smtp->isReady()) {
            throw ValidationException::withMessages(['campaign' => 'Configure SMTP under Super Admin → Email Configuration before sending a campaign.']);
        }

        DB::transaction(function () use ($campaign): void {
            $campaign = EmailCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($campaign->status !== 'draft') {
                throw ValidationException::withMessages(['campaign' => 'This campaign has already been started or cancelled.']);
            }

            $campaign->update(['status' => 'queued', 'started_at' => now()]);
            $batches = (int) ceil($campaign->recipient_count / $campaign->batch_size);

            for ($index = 0; $index < $batches; $index++) {
                SendEmailCampaignBatch::dispatch($campaign->id, $index * $campaign->batch_size, $campaign->batch_size)
                    ->onQueue(config('email_campaigns.queue'))
                    ->delay(now()->addSeconds($index * $campaign->delay_seconds))
                    ->afterCommit();
            }
        });

        return back()->with('success', 'Campaign queued for batch delivery.');
    }

    public function cancel(EmailCampaign $campaign): RedirectResponse
    {
        if (in_array($campaign->status, ['draft', 'queued', 'sending'], true)) {
            $campaign->update(['status' => 'cancelled', 'completed_at' => now()]);
        }

        return back()->with('success', 'Campaign cancelled. Any message already in transit may still finish.');
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
