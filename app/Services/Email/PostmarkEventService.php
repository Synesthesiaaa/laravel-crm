<?php

namespace App\Services\Email;

use App\Jobs\ProcessEmailProviderEvent;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailProviderEvent;
use App\Services\EmailCampaignRecipientTransitionService;
use App\Services\EmailSmtpService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostmarkEventService
{
    public function receive(Request $request): EmailProviderEvent
    {
        abort_unless($request->isSecure(), 403, 'Provider callbacks require HTTPS.');
        abort_if(strlen($request->getContent()) > 65536, 413);
        $configuration = $this->configuration();
        abort_if($configuration['webhook_user'] === '' || $configuration['webhook_password'] === ''
            || $configuration['server_id'] < 1 || $configuration['message_stream'] === '', 503, 'Provider callback is not configured.');
        $authorization = (string) $request->header('Authorization');
        $decoded = str_starts_with($authorization, 'Basic ') ? base64_decode(substr($authorization, 6), true) : false;
        [$user, $password] = explode(':', $decoded ?: ':', 2) + ['', ''];
        abort_unless(hash_equals($configuration['webhook_user'], $user) & hash_equals($configuration['webhook_password'], $password), 401);
        $allowedIps = config('email_campaigns.postmark.allowed_ips', []);
        abort_if($allowedIps && ! in_array($request->ip(), $allowedIps, true), 403);
        $data = Validator::make($request->json()->all(), [
            'RecordType' => ['required', 'in:Delivery,Bounce,SpamComplaint,Open,Click'],
            'ServerID' => ['required', 'integer'], 'MessageStream' => ['required', 'string', 'max:100'],
            'MessageID' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'Metadata.attempt' => ['required', 'uuid'],
            'Recipient' => ['sometimes', 'email:rfc', 'max:255'], 'Email' => ['sometimes', 'email:rfc', 'max:255'],
            'DeliveredAt' => ['sometimes', 'date'], 'BouncedAt' => ['sometimes', 'date'],
            'ReceivedAt' => ['sometimes', 'date'], 'Type' => ['sometimes', 'string', 'max:100'],
            'TypeCode' => ['sometimes', 'integer'],
        ])->validate();
        if ((int) $data['ServerID'] !== $configuration['server_id'] || $data['MessageStream'] !== $configuration['message_stream']) {
            $this->invalid();
        }
        $attempt = DB::table('email_campaign_attempts')->where('id', $data['Metadata']['attempt'])->first();
        $recipient = $attempt ? EmailCampaignRecipient::find($attempt->email_campaign_recipient_id) : null;
        $campaign = $attempt ? EmailCampaign::find($attempt->email_campaign_id) : null;
        $snapshot = $campaign?->delivery_snapshot ?? [];
        $provider = $snapshot['provider'] ?? $campaign?->provider;
        $address = $data['RecordType'] === 'Delivery' ? ($data['Recipient'] ?? '') : ($data['Email'] ?? $data['Recipient'] ?? '');
        if (! $attempt || ! $attempt->entered_transport_at || $attempt->provider !== 'postmark' || $provider !== 'postmark'
            || ! $recipient || ! $campaign || $recipient->email_campaign_id !== $campaign->id
            || ! hash_equals(strtolower($recipient->email), strtolower($address))
            || (isset($snapshot['postmark_server_id']) && (int) $snapshot['postmark_server_id'] !== (int) $data['ServerID'])
            || (isset($snapshot['postmark_message_stream']) && $snapshot['postmark_message_stream'] !== $data['MessageStream'])
            || ($attempt->provider_message_id && ! hash_equals($attempt->provider_message_id, $data['MessageID']))) {
            $this->invalid();
        }
        $type = strtolower($data['RecordType']);
        $timeKey = match ($type) {
            'delivery' => 'DeliveredAt', 'bounce', 'spamcomplaint' => 'BouncedAt', default => 'ReceivedAt'
        };
        if (! isset($data[$timeKey])) {
            $this->invalid();
        }
        $occurred = CarbonImmutable::parse($data[$timeKey])->utc();
        if ($occurred->greaterThan(now()->addMinutes(5)) || $occurred->lessThan(CarbonImmutable::parse($attempt->entered_transport_at)->subMinutes(5))) {
            $this->invalid();
        }
        $bounce = $type === 'bounce' ? (($data['Type'] ?? '') === 'HardBounce' || ($data['TypeCode'] ?? null) === 1 ? 'hard' : 'soft') : null;
        $fingerprint = hash('sha256', json_encode([$attempt->id, $data['MessageID'], $type, $bounce, $occurred->toIso8601String()], JSON_THROW_ON_ERROR));
        $trace = (string) $request->header('X-PM-Webhook-Trace-Id');
        abort_if(strlen($trace) > 255, 422);
        $key = hash('sha256', 'postmark:'.($trace !== '' ? $trace : $fingerprint));
        $event = EmailProviderEvent::firstOrCreate(['event_key' => $key], [
            'fingerprint' => $fingerprint, 'attempt_id' => $attempt->id,
            'email_campaign_id' => $campaign->id, 'email_campaign_recipient_id' => $recipient->id,
            'event_type' => $type, 'bounce_type' => $bounce,
            'provider_message_id' => $data['MessageID'], 'occurred_at' => $occurred,
        ]);
        abort_unless(hash_equals($event->fingerprint, $fingerprint), 409, 'Provider receipt identity conflict.');
        if ($event->wasRecentlyCreated) {
            ProcessEmailProviderEvent::dispatch($event->id)
                ->onConnection(config('email_campaigns.connection'))->onQueue(config('email_campaigns.queue'))->afterCommit();
        }

        return $event;
    }

    public function process(int $id): void
    {
        $event = EmailProviderEvent::find($id);
        if (! $event || $event->processed_at) {
            return;
        }
        DB::transaction(function () use ($event): void {
            // Match sender lock order: campaign, recipient, attempt, receipt.
            $campaign = EmailCampaign::whereKey($event->email_campaign_id)->lockForUpdate()->first();
            $recipient = $campaign?->recipients()->whereKey($event->email_campaign_recipient_id)->lockForUpdate()->first();
            $attempt = DB::table('email_campaign_attempts')->where('id', $event->attempt_id)->lockForUpdate()->first();
            $event = EmailProviderEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->processed_at || ! $campaign || ! $recipient || ! $attempt || ! $attempt->entered_transport_at) {
                return;
            }
            if ($attempt->provider_message_id && $attempt->provider_message_id !== $event->provider_message_id) {
                throw ValidationException::withMessages(['provider' => 'Provider receipt correlation changed.']);
            }
            $tracking = app(EmailTrackingService::class)->settings($campaign);
            $field = match ($event->event_type) {
                'delivery' => 'provider_delivered_at', 'bounce' => 'provider_bounced_at',
                'spamcomplaint' => 'provider_complained_at',
                'open' => $tracking['opens'] ? 'observed_opened_at' : null,
                'click' => $tracking['clicks'] ? 'observed_clicked_at' : null,
                default => null,
            };
            if ($field && (! $recipient->getAttribute($field) || $event->occurred_at->lessThan($recipient->getAttribute($field)))) {
                $recipient->forceFill([$field => $event->occurred_at])->save();
            }
            if ($event->event_type === 'bounce' && ($event->bounce_type === 'hard' || $recipient->provider_bounce_type !== 'hard')) {
                $recipient->forceFill(['provider_bounce_type' => $event->bounce_type])->save();
            }
            if ($event->event_type === 'spamcomplaint' || ($event->event_type === 'bounce' && $event->bounce_type === 'hard')) {
                $suppression = app(SuppressionService::class)->suppress($recipient->email, $event->event_type === 'spamcomplaint' ? 'complaint' : 'hard_bounce', 'all', 'postmark');
                if ($suppression->wasRecentlyCreated) {
                    activity('email-campaign')->event('provider-suppression')
                        ->withProperties(['suppression_id' => $suppression->id, 'provider_event_id' => $event->id, 'campaign_id' => $campaign->id])
                        ->log('email_campaign.provider_suppression');
                }
            }
            // All supported authenticated events prove provider acceptance of this attempt.
            DB::table('email_campaign_attempts')->where('id', $attempt->id)->update([
                'status' => 'accepted', 'provider_message_id' => $event->provider_message_id,
                'accepted_at' => $attempt->accepted_at ?? $event->occurred_at, 'updated_at' => now(),
            ]);
            if (in_array($recipient->status, ['sending', 'uncertain'], true)) {
                app(EmailCampaignRecipientTransitionService::class)->transition($campaign->id, $recipient->id, $recipient->status, 'accepted', ['sent_at' => $recipient->sent_at ?? $event->occurred_at, 'error' => null]);
            } elseif (! in_array($recipient->status, ['accepted', 'sent'], true)) {
                // A late verified event can contradict a prior no-submission reconciliation.
                // This trusted correction is not a user-selected lifecycle transition.
                $recipient->forceFill(['status' => 'accepted', 'sent_at' => $recipient->sent_at ?? $event->occurred_at, 'error' => null])->save();
                app(EmailCampaignRecipientTransitionService::class)->reconcile($campaign->id);
            }
            $event->forceFill(['processed_at' => now()])->save();
        });
    }

    public function configuration(): array
    {
        $settings = app(EmailSmtpService::class)->activeSettings();
        $result = [];
        foreach (['webhook_user', 'webhook_password', 'server_id', 'message_stream'] as $key) {
            $result[$key] = $settings?->getAttribute('postmark_'.$key) ?? config('email_campaigns.postmark.'.$key, '');
        }
        $result['server_id'] = (int) $result['server_id'];
        foreach (['webhook_user', 'webhook_password', 'message_stream'] as $key) {
            $result[$key] = (string) $result[$key];
        }

        return $result;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['provider' => 'Provider identity, timestamp or recipient correlation is invalid.']);
    }
}
