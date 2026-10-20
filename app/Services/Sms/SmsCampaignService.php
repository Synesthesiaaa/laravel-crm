<?php

namespace App\Services\Sms;

use App\Models\SmsCampaign;
use App\Models\SmsConsent;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SmsCampaignService
{
    public function audit(User $actor, string $action, SmsCampaign|\App\Models\SmsGateway|null $subject = null): void
    {
        $log = activity('sms')->causedBy($actor);
        if ($subject) {
            $log->performedOn($subject);
        }
        $log->log($action);
    }

    public function addRecipient(SmsCampaign $campaign, array $row, User $actor, ?int $leadId = null): void
    {
        DB::transaction(function () use ($campaign, $row, $leadId): void {
            $campaign = SmsCampaign::lockForUpdate()->findOrFail($campaign->id);
            abort_unless($campaign->status === 'draft', 409, 'Only drafts accept recipient changes.');
            if ($campaign->messages()->count() >= config('sms.max_recipients')) {
                throw ValidationException::withMessages(['recipients' => 'Campaign recipient limit reached.']);
            }
            $status = 'eligible';
            $phone = null;
            $consent = null;
            $body = null;

            try {
                $phone = SmsText::phone((string) ($row['phone'] ?? ''));
            } catch (InvalidArgumentException) {
                $status = 'invalid';
            }
            if ($phone && $campaign->messages()->where('phone', $phone)->exists()) {
                $status = 'duplicate';
            } elseif ($phone) {
                $consent = SmsConsent::where('phone', $phone)->where('category', $campaign->category)->whereNull('revoked_at')->first();
                if (! $consent) {
                    $status = 'no_consent';
                }
                if ($campaign->category === 'marketing' && SmsOptOut::where('phone', $phone)->exists()) {
                    $status = 'suppressed';
                }

                try {
                    $body = SmsText::render($campaign->body, mb_substr((string) ($row['recipient_name'] ?? ''), 0, 150), $campaign->name);
                    SmsText::assertSupported($body);
                } catch (InvalidArgumentException) {
                    $status = 'unsupported';
                }
            }
            $campaign->messages()->create([
                'phone' => $phone, 'recipient_name' => mb_substr((string) ($row['recipient_name'] ?? ''), 0, 150),
                'lead_hopper_id' => $leadId, 'sms_consent_id' => $consent?->id,
                'status' => $status, 'body' => $body, 'idempotency_key' => (string) Str::uuid(),
            ]);
            $campaign->increment('revision');
        });
    }

    public function recordConsent(array $row, string $category, User $actor): SmsConsent
    {
        try {
            $phone = SmsText::phone((string) ($row['phone'] ?? ''));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }
        $data = validator($row, ['source' => 'required|string|max:255', 'consented_at' => 'required|date|before_or_equal:now'])->validate();
        $consent = SmsConsent::updateOrCreate(['phone' => $phone, 'category' => $category], [
            'source' => $data['source'], 'consented_at' => \Carbon\Carbon::parse($data['consented_at'])->timezone(config('app.timezone')), 'recorded_by' => $actor->id, 'revoked_at' => null,
        ]);
        $this->audit($actor, 'Consent evidence recorded');

        // A consent edit deliberately does not remove any opt-out.
        return $consent;
    }

    public function eligible(SmsMessage $message, SmsCampaign $campaign): bool
    {
        return $message->sms_consent_id
            && SmsConsent::whereKey($message->sms_consent_id)->where('phone', $message->phone)
                ->where('category', $campaign->category)->whereNull('revoked_at')->where('consented_at', '<=', now())->exists()
            && ($campaign->category !== 'marketing' || ! SmsOptOut::where('phone', $message->phone)->exists());
    }

    public function preview(SmsCampaign $campaign): array
    {
        $campaign->refresh();
        $hash = hash_init('sha256');
        hash_update($hash, json_encode([$campaign->id, $campaign->revision, $campaign->body, $campaign->channel_ids, $campaign->scheduled_at, $campaign->quiet_start, $campaign->quiet_end]));
        $counts = [];
        $currentConsent = fn ($query) => $query->whereColumn('sms_consents.phone', 'sms_messages.phone')->where('category', $campaign->category)->whereNull('revoked_at')->where('consented_at', '<=', now());
        foreach ($campaign->messages()->withExists(['consent as has_current_consent' => $currentConsent, 'optOut'])->orderBy('id')->cursor() as $message) {
            $status = $message->status;
            if ($status === 'eligible' && (! $message->has_current_consent || ($campaign->category === 'marketing' && $message->opt_out_exists))) {
                $status = 'suppressed';
            }
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            hash_update($hash, "{$message->id}:{$status}:{$message->phone};");
        }

        return ['counts' => $counts, 'digest' => hash_final($hash), 'estimate' => SmsText::estimate($campaign->body)];
    }

    public function confirm(SmsCampaign $campaign, string $digest, bool $large, User $actor): void
    {
        DB::transaction(function () use ($campaign, $digest, $large, $actor): void {
            $campaign = SmsCampaign::lockForUpdate()->findOrFail($campaign->id);
            abort_unless($campaign->status === 'draft', 409);
            $preview = $this->preview($campaign);
            if (! hash_equals($preview['digest'], $digest) || ($preview['counts']['eligible'] ?? 0) < 1) {
                throw ValidationException::withMessages(['audience' => 'Audience changed or has no eligible recipients. Review it again.']);
            }
            if (($preview['counts']['eligible'] ?? 0) >= config('sms.large_send_threshold') && ! $large) {
                throw ValidationException::withMessages(['large_send' => 'Explicit large-send confirmation is required.']);
            }

            try {
                app(GoipHttpTransport::class)->assertReady($campaign->gateway);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['gateway' => $e->getMessage()]);
            }
            $channels = $campaign->gateway->channels()->whereIn('id', $campaign->channel_ids)->where('enabled', true)->count();
            if (! $channels || $channels !== count($campaign->channel_ids)) {
                throw ValidationException::withMessages(['channels' => 'Selected channels must belong to this gateway and be enabled.']);
            }
            $currentConsent = fn ($query) => $query->whereColumn('sms_consents.phone', 'sms_messages.phone')->where('category', $campaign->category)->whereNull('revoked_at')->where('consented_at', '<=', now());
            $campaign->messages()->where('status', 'eligible')->where(function ($query) use ($currentConsent, $campaign): void {
                $query->whereDoesntHave('consent', $currentConsent);
                if ($campaign->category === 'marketing') {
                    $query->orWhereHas('optOut');
                }
            })->update(['status' => 'canceled', 'reason' => 'consent_or_suppression_changed']);
            $campaign->messages()->where('status', 'eligible')->update(['status' => 'queued']);
            $campaign->update(['status' => $campaign->scheduled_at?->isFuture() ? 'scheduled' : 'queued', 'confirmed_at' => now()]);
            $this->audit($actor, 'Campaign audience confirmed', $campaign);
        });
    }

    public function transition(SmsCampaign $campaign, string $action, User $actor): void
    {
        DB::transaction(function () use ($campaign, $action, $actor): void {
            $campaign = SmsCampaign::lockForUpdate()->findOrFail($campaign->id);
            $next = match ($action) {
                'pause' => in_array($campaign->status, ['queued', 'scheduled', 'running'], true) ? 'paused' : null,
                'resume' => $campaign->status === 'paused' ? ($campaign->scheduled_at?->isFuture() ? 'scheduled' : 'queued') : null,
                'cancel' => in_array($campaign->status, ['draft', 'scheduled', 'queued', 'running', 'paused'], true) ? 'canceled' : null,
                default => null,
            };
            abort_unless($next, 409, 'Invalid campaign transition.');
            $campaign->update(['status' => $next]);
            if ($next === 'canceled') {
                $campaign->messages()->whereIn('status', ['eligible', 'queued'])->update(['status' => 'canceled', 'reason' => 'campaign_canceled']);
            }
            $this->audit($actor, "Campaign {$action}", $campaign);
        });
    }
}
