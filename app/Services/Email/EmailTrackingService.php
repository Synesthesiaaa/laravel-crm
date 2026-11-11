<?php

namespace App\Services\Email;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailOptOut;
use App\Models\EmailTrackingToken;
use DOMDocument;
use Illuminate\Support\Facades\DB;

class EmailTrackingService
{
    public function settings(EmailCampaign $campaign): array
    {
        $snapshot = $campaign->delivery_snapshot ?? [];
        $purpose = $snapshot['purpose'] ?? $campaign->purpose;
        $mode = $snapshot['attachment_mode'] ?? $campaign->attachment_mode;
        if ($purpose !== 'marketing' || $mode !== 'none' || ($snapshot['template']['pdf_enabled'] ?? $campaign->template?->pdf_enabled)) {
            return ['opens' => false, 'clicks' => false];
        }

        return [
            'opens' => (bool) ($snapshot ? ($snapshot['tracking']['opens'] ?? false) : ($campaign->tracking_opens ?? false)),
            'clicks' => (bool) ($snapshot ? ($snapshot['tracking']['clicks'] ?? false) : ($campaign->tracking_clicks ?? false)),
        ];
    }

    public function decorate(string $html, EmailCampaign $campaign, EmailCampaignRecipient $recipient, ?string $attemptId): string
    {
        abort_unless((int) $recipient->email_campaign_id === (int) $campaign->id, 422);
        $settings = $this->settings($campaign);
        if (! $settings['opens'] && ! $settings['clicks']) {
            return $html;
        }
        if ($settings['clicks']) {
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);

            try {
                $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                foreach ($document->getElementsByTagName('a') as $link) {
                    $target = $link->getAttribute('href');
                    if (! $link->hasAttribute('data-no-track') && $this->safeDestination($target)
                        && ! str_contains(parse_url($target, PHP_URL_PATH) ?: '', '/unsubscribe/')) {
                        $link->setAttribute('href', route('email-tracking.click', ['token' => $this->token('click', $campaign, $recipient, $attemptId, $target)]));
                    }
                }
                $html = str_replace('<?xml encoding="utf-8" ?>', '', $document->saveHTML());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }
        if ($settings['opens']) {
            $url = route('email-tracking.open', ['token' => $this->token('open', $campaign, $recipient, $attemptId)]);
            $pixel = '<img src="'.e($url).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0" />';
            $html = str_contains($html, '</body>') ? str_replace('</body>', $pixel.'</body>', $html) : $html.$pixel;
        }

        return $html;
    }

    public function unsubscribeUrl(EmailCampaign $campaign, EmailCampaignRecipient $recipient): string
    {
        abort_unless(($campaign->delivery_snapshot['purpose'] ?? $campaign->purpose) === 'marketing'
            && (int) $recipient->email_campaign_id === (int) $campaign->id, 404);

        return route('email-tracking.unsubscribe', ['token' => $this->token('unsubscribe', $campaign, $recipient, null)]);
    }

    public function resolve(string $raw, string $kind): EmailTrackingToken
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $raw), 404);
        $token = EmailTrackingToken::where('token_hash', hash('sha256', $raw))->where('kind', $kind)->firstOrFail();
        abort_if($token->expires_at?->isPast(), 410);

        return $token;
    }

    public function observe(EmailTrackingToken $token, bool $privacySignal): void
    {
        if ($privacySignal && config('email_campaigns.tracking_respect_privacy', true)) {
            return;
        }
        $campaign = EmailCampaign::find($token->email_campaign_id);
        if (! $campaign || ! in_array($token->kind, ['open', 'click'], true)) {
            return;
        }
        $settings = $this->settings($campaign);
        if (! $settings[$token->kind === 'open' ? 'opens' : 'clicks']) {
            return;
        }
        $field = $token->kind === 'open' ? 'observed_opened_at' : 'observed_clicked_at';
        EmailCampaignRecipient::whereKey($token->email_campaign_recipient_id)->where('email_campaign_id', $campaign->id)
            ->whereNull($field)->update([$field => now()]);
    }

    public function unsubscribe(EmailTrackingToken $token): void
    {
        abort_unless($token->kind === 'unsubscribe', 404);
        DB::transaction(function () use ($token): void {
            $campaign = EmailCampaign::whereKey($token->email_campaign_id)->lockForUpdate()->firstOrFail();
            abort_unless(($campaign->delivery_snapshot['purpose'] ?? $campaign->purpose) === 'marketing', 404);
            $recipient = $campaign->recipients()->whereKey($token->email_campaign_recipient_id)->lockForUpdate()->firstOrFail();
            EmailOptOut::firstOrCreate(['email' => strtolower($recipient->email)]);
            app(SuppressionService::class)->suppress($recipient->email, 'unsubscribe', 'marketing', 'unsubscribe');
            if (! $recipient->unsubscribed_at) {
                $recipient->forceFill(['unsubscribed_at' => now()])->save();
                activity('email-campaign')->event('unsubscribe')
                    ->withProperties(['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id])
                    ->log('email_campaign.unsubscribe');
            }
        });
    }

    public function safeDestination(string $destination): bool
    {
        if (strlen($destination) > 2048 || preg_match('/[\x00-\x20\x7f]/', $destination)) {
            return false;
        }
        $url = parse_url($destination);

        return $url !== false && in_array(strtolower($url['scheme'] ?? ''), ['https', 'http'], true)
            && ! empty($url['host']) && ! isset($url['user']) && ! isset($url['pass']);
    }

    private function token(string $kind, EmailCampaign $campaign, EmailCampaignRecipient $recipient, ?string $attemptId, ?string $destination = null): string
    {
        $identity = implode('|', [$kind, $campaign->id, $recipient->id, $attemptId, hash('sha256', $destination ?? '')]);
        $raw = hash_hmac('sha256', 'email-tracking:'.$identity, app('encrypter')->getKey());
        EmailTrackingToken::firstOrCreate(['token_hash' => hash('sha256', $raw)], [
            'kind' => $kind, 'email_campaign_id' => $campaign->id,
            'email_campaign_recipient_id' => $recipient->id, 'attempt_id' => $attemptId,
            'destination' => $destination,
            'expires_at' => $kind === 'unsubscribe' ? null : now()->addDays(max(1, (int) config('email_campaigns.tracking_retention_days', 90))),
        ]);

        return $raw;
    }
}
