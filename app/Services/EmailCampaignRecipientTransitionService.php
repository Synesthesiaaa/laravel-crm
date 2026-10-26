<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailCampaignRecipientTransitionService
{
    private const ALLOWED = [
        'pending' => ['sending', 'cancelled', 'skipped', 'blocked'],
        'sending' => ['accepted', 'sent', 'skipped', 'blocked', 'uncertain', 'failed'],
        'blocked' => ['pending'],
        'uncertain' => ['accepted', 'blocked'],
    ];

    public function claim(int $campaignId, int $recipientId): ?EmailCampaignRecipient
    {
        return DB::transaction(function () use ($campaignId, $recipientId): ?EmailCampaignRecipient {
            $campaign = EmailCampaign::query()->whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign || ! in_array($campaign->status, ['queued', 'sending'], true)) {
                return null;
            }

            $recipient = $campaign->recipients()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $recipient || $recipient->status !== 'pending') {
                return null;
            }

            $this->initializeCounters($campaign);
            $recipient->forceFill(['status' => 'sending', 'claimed_at' => now(), 'error' => null])->save();
            $campaign->status = 'sending';
            $campaign->save();

            return $recipient;
        });
    }

    public function transition(int $campaignId, int $recipientId, string $from, string $to, array $attributes = []): bool
    {
        $this->assertAllowed($from, $to);

        return DB::transaction(function () use ($campaignId, $recipientId, $from, $to, $attributes): bool {
            $campaign = EmailCampaign::query()->whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign) {
                return false;
            }

            $recipient = $campaign->recipients()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $recipient || $recipient->status !== $from) {
                return false;
            }

            $this->initializeCounters($campaign);
            $recipient->forceFill([...$attributes, 'status' => $to])->save();
            $this->applyDelta($campaign, $from, $to, 1);
            $this->saveCompletionState($campaign);

            return true;
        });
    }

    /** @param list<int> $recipientIds */
    public function transitionMany(int $campaignId, array $recipientIds, string $from, string $to, array $attributes = []): int
    {
        $this->assertAllowed($from, $to);
        if ($recipientIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($campaignId, $recipientIds, $from, $to, $attributes): int {
            $campaign = EmailCampaign::query()->whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign) {
                return 0;
            }

            $recipients = $campaign->recipients()->whereIn('id', $recipientIds)->where('status', $from);
            $count = (clone $recipients)->count();
            if ($count === 0) {
                return 0;
            }

            $this->initializeCounters($campaign);
            $recipients->update([...$attributes, 'status' => $to]);
            $this->applyDelta($campaign, $from, $to, $count);
            $this->saveCompletionState($campaign);

            return $count;
        });
    }

    public function transitionAll(int $campaignId, string $from, string $to, array $attributes = []): int
    {
        $this->assertAllowed($from, $to);

        return DB::transaction(function () use ($campaignId, $from, $to, $attributes): int {
            $campaign = EmailCampaign::query()->whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign) {
                return 0;
            }

            $recipients = $campaign->recipients()->where('status', $from);
            $count = (clone $recipients)->count();
            if ($count === 0) {
                return 0;
            }

            $this->initializeCounters($campaign);
            $recipients->update([...$attributes, 'status' => $to]);
            $this->applyDelta($campaign, $from, $to, $count);
            $this->saveCompletionState($campaign);

            return $count;
        });
    }

    public function reconcile(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId): void {
            $campaign = EmailCampaign::query()->whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign) {
                return;
            }

            $counts = $campaign->recipients()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $campaign->forceFill([
                'sent_count' => ($counts['accepted'] ?? 0) + ($counts['sent'] ?? 0),
                'failed_count' => ($counts['failed'] ?? 0) + ($counts['blocked'] ?? 0) + ($counts['uncertain'] ?? 0),
                'skipped_count' => $counts['skipped'] ?? 0,
                'open_count' => ($counts['pending'] ?? 0) + ($counts['sending'] ?? 0),
                'attention_count' => ($counts['blocked'] ?? 0) + ($counts['uncertain'] ?? 0),
            ])->save();
            $this->saveCompletionState($campaign);
        });
    }

    private function initializeCounters(EmailCampaign $campaign): void
    {
        if ($campaign->open_count !== null && $campaign->attention_count !== null) {
            return;
        }

        $counts = $campaign->recipients()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $campaign->forceFill([
            'sent_count' => ($counts['accepted'] ?? 0) + ($counts['sent'] ?? 0),
            'failed_count' => ($counts['failed'] ?? 0) + ($counts['blocked'] ?? 0) + ($counts['uncertain'] ?? 0),
            'skipped_count' => $counts['skipped'] ?? 0,
            'open_count' => ($counts['pending'] ?? 0) + ($counts['sending'] ?? 0),
            'attention_count' => ($counts['blocked'] ?? 0) + ($counts['uncertain'] ?? 0),
        ]);
    }

    private function applyDelta(EmailCampaign $campaign, string $from, string $to, int $count): void
    {
        $delta = [];
        foreach ([$from => -$count, $to => $count] as $status => $amount) {
            $field = match ($status) {
                'accepted', 'sent' => 'sent_count',
                'failed', 'blocked', 'uncertain' => 'failed_count',
                'skipped' => 'skipped_count',
                'pending', 'sending' => 'open_count',
                default => null,
            };
            if ($field) {
                $delta[$field] = ($delta[$field] ?? 0) + $amount;
            }
            if (in_array($status, ['blocked', 'uncertain'], true)) {
                $delta['attention_count'] = ($delta['attention_count'] ?? 0) + $amount;
            }
        }

        foreach ($delta as $field => $amount) {
            $campaign->{$field} = max(0, (int) $campaign->{$field} + $amount);
        }
        $campaign->save();
    }

    private function saveCompletionState(EmailCampaign $campaign): void
    {
        if ((int) $campaign->open_count === 0 && in_array($campaign->status, ['queued', 'sending', 'needs_attention'], true)) {
            $campaign->status = (int) $campaign->attention_count > 0 ? 'needs_attention' : 'completed';
            $campaign->completed_at = now();
            $campaign->save();
        }
    }

    private function assertAllowed(string $from, string $to): void
    {
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw ValidationException::withMessages(['campaign' => 'The requested recipient status transition is not allowed.']);
        }
    }
}
