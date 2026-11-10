<?php

namespace App\Services\Email;

use App\Models\EmailCampaign;

class CampaignMetrics
{
    public function forCampaign(EmailCampaign $campaign): array
    {
        $row = $campaign->recipients()->selectRaw("COUNT(*) as total,
            SUM(CASE WHEN status IN ('accepted','sent') OR provider_delivered_at IS NOT NULL OR provider_bounced_at IS NOT NULL OR provider_complained_at IS NOT NULL THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN provider_delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN provider_bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced,
            SUM(CASE WHEN provider_bounce_type = 'hard' THEN 1 ELSE 0 END) as hard_bounced,
            SUM(CASE WHEN provider_bounce_type = 'soft' THEN 1 ELSE 0 END) as soft_bounced,
            SUM(CASE WHEN provider_complained_at IS NOT NULL THEN 1 ELSE 0 END) as complained,
            SUM(CASE WHEN observed_opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN observed_clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
            SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
            SUM(CASE WHEN status = 'uncertain' THEN 1 ELSE 0 END) as uncertain,
            SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status IN ('pending','sending') THEN 1 ELSE 0 END) as pending")->first();
        $metrics = [];
        foreach (['total', 'accepted', 'delivered', 'bounced', 'hard_bounced', 'soft_bounced', 'complained', 'opened', 'clicked', 'unsubscribed', 'failed', 'blocked', 'uncertain', 'skipped', 'cancelled', 'pending'] as $key) {
            $metrics[$key] = (int) ($row?->{$key} ?? 0);
        }
        $snapshot = $campaign->delivery_snapshot ?? [];
        $provider = $snapshot['provider'] ?? $campaign->provider ?? 'smtp';
        $tracking = app(EmailTrackingService::class)->settings($campaign);
        $configuration = app(PostmarkEventService::class)->configuration();
        $metrics['provider_coverage'] = $provider === 'postmark' && $configuration['server_id'] > 0
            && $configuration['webhook_user'] !== '' && $configuration['webhook_password'] !== '' && $configuration['message_stream'] !== '';
        $metrics['engagement_basis'] = $metrics['provider_coverage'] ? 'delivered' : 'accepted';
        $denominator = $metrics[$metrics['engagement_basis']];
        $metrics['delivery_rate'] = $metrics['provider_coverage'] ? $this->rate($metrics['delivered'], $metrics['accepted']) : null;
        $metrics['bounce_rate'] = $metrics['provider_coverage'] ? $this->rate($metrics['bounced'], $metrics['accepted']) : null;
        $metrics['open_rate'] = $tracking['opens'] ? $this->rate($metrics['opened'], $denominator) : null;
        $metrics['click_rate'] = $tracking['clicks'] ? $this->rate($metrics['clicked'], $denominator) : null;
        if (! $metrics['provider_coverage']) {
            foreach (['delivered', 'bounced', 'hard_bounced', 'soft_bounced', 'complained'] as $key) {
                $metrics[$key] = null;
            }
        }
        if (! $tracking['opens']) {
            $metrics['opened'] = null;
        }
        if (! $tracking['clicks']) {
            $metrics['clicked'] = null;
        }
        $metrics['processed'] = $metrics['total'] - $metrics['pending'];
        $metrics['progress_percent'] = $metrics['total'] > 0 ? round(100 * $metrics['processed'] / $metrics['total'], 1) : 0.0;
        $metrics['engagement_note'] = 'Observed opens and clicks may include link scanners and privacy proxies.';

        return $metrics;
    }

    private function rate(int $value, int $denominator): ?float
    {
        return $denominator > 0 ? round(100 * $value / $denominator, 2) : null;
    }
}
