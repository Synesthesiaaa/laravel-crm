<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailOptOut;
use App\Services\EmailCampaignDocumentService;
use App\Services\EmailSmtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendEmailCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $campaignId,
        public int $offset,
        public int $size,
    ) {}

    public function handle(EmailCampaignDocumentService $documents, EmailSmtpService $smtp): void
    {
        $campaign = EmailCampaign::with('template')->find($this->campaignId);
        if (! $campaign || ! in_array($campaign->status, ['queued', 'sending'], true)) {
            return;
        }
        $campaign->update(['status' => 'sending']);
        $mailer = $smtp->mailer();
        [$fromAddress, $fromName] = $smtp->sender();

        // The offset is applied to all campaign recipients, including previously sent rows.
        $recipients = $campaign->recipients()->orderBy('id')->offset($this->offset)->limit($this->size)->get();
        foreach ($recipients as $recipient) {
            $campaign->refresh();
            if ($campaign->status === 'cancelled') {
                break;
            }
            if (EmailCampaignRecipient::whereKey($recipient->id)->where('status', 'pending')->update(['status' => 'sending']) !== 1) {
                continue;
            }

            if (EmailOptOut::where('email', $recipient->email)->exists()) {
                $recipient->update(['status' => 'skipped']);
                $campaign->increment('skipped_count');

                continue;
            }

            try {
                $template = $campaign->template;
                $unsubscribeUrl = URL::signedRoute('email-campaigns.unsubscribe', ['recipient' => $recipient->id]);
                $body = $documents->personalize($template->html_body, $recipient->name ?: 'there', $recipient->email);
                $subject = $documents->personalize($template->subject, $recipient->name ?: 'there', $recipient->email);
                $html = '<div style="white-space:normal">'.nl2br(e($body)).'</div>'
                    .'<p style="font-size:12px;color:#666">You are receiving this message because you subscribed to updates. '
                    .'<a href="'.e($unsubscribeUrl).'">Unsubscribe</a></p>';

                // Mail is sent individually; recipient addresses never appear in CC/BCC.
                $mailer->html($html, function (Message $message) use ($recipient, $template, $subject, $unsubscribeUrl, $documents, $fromAddress, $fromName): void {
                    $message->from($fromAddress, $fromName)
                        ->to($recipient->email, $recipient->name ?: null)
                        ->subject($subject);
                    $message->getSymfonyMessage()->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
                    if ($template->pdf_enabled) {
                        $message->attachData(
                            $documents->renderPdf($template, $recipient->name ?: 'Recipient', $recipient->email),
                            'document.pdf',
                            ['mime' => 'application/pdf'],
                        );
                    }
                });

                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $campaign->increment('sent_count');
            } catch (Throwable $e) {
                report($e);
                $recipient->update(['status' => 'failed', 'error' => 'Delivery failed; review server mail logs.']);
                $campaign->increment('failed_count');
            }
        }

        $this->completeIfFinished($campaign);
    }

    public function failed(?Throwable $exception): void
    {
        $campaign = EmailCampaign::find($this->campaignId);
        if (! $campaign || $campaign->status === 'cancelled') {
            return;
        }

        $recipients = $campaign->recipients()->orderBy('id')->offset($this->offset)->limit($this->size)->get();
        foreach ($recipients as $recipient) {
            if (in_array($recipient->status, ['pending', 'sending'], true)) {
                $recipient->update(['status' => 'failed', 'error' => 'Processing interrupted; review worker logs.']);
                $campaign->increment('failed_count');
            }
        }
        $this->completeIfFinished($campaign);
    }

    private function completeIfFinished(EmailCampaign $campaign): void
    {
        if (! $campaign->recipients()->whereIn('status', ['pending', 'sending'])->exists()) {
            EmailCampaign::whereKey($campaign->id)
                ->whereIn('status', ['queued', 'sending'])
                ->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
