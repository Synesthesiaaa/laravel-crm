<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendEmailCampaignRecipient;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailCampaignDeliveryService;
use App\Services\EmailCampaignPreflightService;
use App\Services\EmailCampaignQueueStatus;
use App\Services\EmailCampaignSendingPolicy;
use App\Services\EmailSmtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailCampaignLocalSendingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Queue::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        config([
            'email_campaigns.live_enabled' => true, 'email_campaigns.allow_local' => true,
            'email_campaigns.connection' => 'email-database', 'mail.default' => 'synthetic-smtp',
            'mail.mailers.synthetic-smtp' => ['transport' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'timeout' => 15],
        ]);
        $this->smtp();
        $this->worker();
    }

    public function test_local_opt_in_queues_only_after_existing_confirmation(): void
    {
        $campaign = $this->draft();
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.send', $campaign))->assertSessionHasErrors('confirmed_mapping');
        Queue::assertNotPushed(SendEmailCampaignRecipient::class);
        $this->post(route('admin.email-campaigns.send', $campaign), $this->confirmation())->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmailCampaignRecipient::class, 1);
        $this->assertSame('queued', $campaign->fresh()->status);
    }

    public function test_local_requires_both_live_and_local_opt_in(): void
    {
        $campaign = $this->draft();
        config(['email_campaigns.allow_local' => false]);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.send', $campaign), $this->confirmation())->assertSessionHasErrors('campaign');
        config(['email_campaigns.allow_local' => true, 'email_campaigns.live_enabled' => false]);
        $this->post(route('admin.email-campaigns.send', $campaign), $this->confirmation())->assertSessionHasErrors('campaign');
        Queue::assertNotPushed(SendEmailCampaignRecipient::class);
    }

    public function test_additive_delivery_columns_keep_legacy_defaults_without_counter_backfill(): void
    {
        $campaign = $this->draft();

        $this->assertSame(1, $campaign->fresh()->delivery_snapshot_version);
        $this->assertNull($campaign->fresh()->delivery_snapshot_hash);
        $this->assertNull($campaign->fresh()->open_count);
        $this->assertNull($campaign->fresh()->attention_count);
        $this->assertNull($campaign->recipients()->firstOrFail()->delivery_snapshot_hash);
    }

    public function test_local_still_requires_smtp_and_worker_readiness(): void
    {
        $campaign = $this->draft();
        $this->smtp(false);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.send', $campaign), $this->confirmation())->assertSessionHasErrors('campaign');
        $this->smtp();
        $this->worker('Worker heartbeat missing.');
        $this->post(route('admin.email-campaigns.send', $campaign), $this->confirmation())->assertSessionHasErrors('campaign');
        Queue::assertNotPushed(SendEmailCampaignRecipient::class);
    }

    public function test_worker_uses_same_local_policy_with_a_non_sending_transport(): void
    {
        $campaign = $this->frozen();
        $this->assertSame(2, $campaign->delivery_snapshot_version);
        $this->assertArrayNotHasKey('recipients', $campaign->delivery_snapshot);
        $this->assertArrayNotHasKey('documents', $campaign->delivery_snapshot);
        $this->assertNotEmpty($campaign->delivery_snapshot_hash);
        $recipient = $campaign->recipients()->firstOrFail();
        $this->assertNotEmpty($recipient->delivery_snapshot_hash);
        $arrayMailer = Mail::mailer('array');
        Mail::shouldReceive('build')->once()->andReturn($arrayMailer);
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
        $this->assertSame(0, $campaign->fresh()->skipped_count);
        $this->assertSame(0, $campaign->fresh()->open_count);
        $this->assertSame(0, $campaign->fresh()->attention_count);
        $messages = $arrayMailer->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages[0]->getOriginalMessage();
        $this->assertCount(1, $message->getTo());
        $this->assertSame('synthetic@example.com', $message->getTo()[0]->getAddress());
        $this->assertSame([], $message->getCc());
        $this->assertSame([], $message->getBcc());
    }

    public function test_tampered_v2_campaign_header_or_recipient_snapshot_is_blocked_before_transport(): void
    {
        foreach (['header', 'recipient'] as $target) {
            $campaign = $this->frozen();
            $recipient = $campaign->recipients()->firstOrFail();
            if ($target === 'header') {
                $snapshot = $campaign->delivery_snapshot;
                $snapshot['template']['subject'] = 'Tampered';
                $campaign->forceFill(['delivery_snapshot' => $snapshot])->save();
            } else {
                $snapshot = $recipient->delivery_snapshot;
                $snapshot['name'] = 'Tampered';
                $recipient->forceFill(['delivery_snapshot' => $snapshot])->save();
            }
            Mail::shouldReceive('build')->never();

            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);

            $this->assertSame('blocked', $recipient->fresh()->status);
            $this->assertSame(1, $campaign->fresh()->failed_count);
            $this->assertSame(1, $campaign->fresh()->attention_count);
            $this->assertSame(0, $campaign->fresh()->open_count);
        }
    }

    public function test_version_one_full_manifest_campaign_remains_deliverable(): void
    {
        $campaign = $this->draft();
        $recipient = $campaign->recipients()->firstOrFail();
        $result = app(EmailCampaignPreflightService::class)->inspect($campaign);
        $campaign->forceFill([
            'delivery_snapshot' => $result['snapshot'],
            'manifest_hash' => $result['hash'],
            'delivery_snapshot_version' => 1,
            'status' => 'queued',
            'open_count' => 1,
        ])->save();
        $recipient->forceFill(['delivery_snapshot' => $result['snapshot']['recipients'][0]])->save();
        $arrayMailer = Mail::mailer('array');
        Mail::shouldReceive('build')->once()->andReturn($arrayMailer);

        app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);

        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->open_count);
        $this->assertCount(1, $arrayMailer->getSymfonyTransport()->messages());
    }

    public function test_v2_delivery_uses_the_selected_recipient_without_campaign_wide_recounts(): void
    {
        $campaign = $this->frozen();
        $campaign->forceFill(['open_count' => 1, 'attention_count' => 0])->save();
        $recipient = $campaign->recipients()->firstOrFail();
        $arrayMailer = Mail::mailer('array');
        Mail::shouldReceive('build')->once()->andReturn($arrayMailer);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $recipientQueries = collect($queries)->pluck('query')->map('strtolower')
            ->filter(fn (string $sql): bool => str_contains($sql, 'email_campaign_recipients'));
        $this->assertNotEmpty($recipientQueries);
        $this->assertSame([], $recipientQueries->filter(fn (string $sql): bool => str_contains($sql, 'group by'))->values()->all());
        $this->assertSame([], $recipientQueries->filter(fn (string $sql): bool => str_contains($sql, 'select * from "email_campaign_recipients"') && ! str_contains($sql, '"id" = ?'))->values()->all());
    }

    public function test_worker_blocks_when_local_opt_in_is_revoked(): void
    {
        $campaign = $this->frozen();
        config(['email_campaigns.allow_local' => false]);
        Mail::shouldReceive('build')->never();
        $recipient = $campaign->recipients()->firstOrFail();
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        $this->assertSame('blocked', $recipient->fresh()->status);
    }

    public function test_testing_environment_cannot_enable_a_real_transport(): void
    {
        $campaign = $this->frozen();
        $this->app['env'] = 'testing';
        $this->assertFalse(app(EmailCampaignSendingPolicy::class)->allowsLive());
        Mail::shouldReceive('build')->never();
        $recipient = $campaign->recipients()->firstOrFail();
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        $this->assertSame('blocked', $recipient->fresh()->status);
    }

    public function test_revalidated_local_retry_uses_the_same_opt_in(): void
    {
        $campaign = $this->frozen();
        $campaign->recipients()->update(['status' => 'blocked']);
        $campaign->update(['status' => 'needs_attention']);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.retry', $campaign), ['confirmed_retry' => 1])->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmailCampaignRecipient::class, 1);
        $this->assertSame(0, $campaign->fresh()->failed_count);
        $this->assertSame(0, $campaign->fresh()->attention_count);
        $this->assertSame(1, $campaign->fresh()->open_count);
    }

    private function draft(): EmailCampaign
    {
        $template = EmailTemplate::create(['name' => 'Synthetic', 'subject' => 'Synthetic notice', 'html_body' => 'Synthetic text only.']);
        $campaign = EmailCampaign::create(['name' => 'Local policy regression', 'email_template_id' => $template->id, 'purpose' => 'transactional', 'attachment_mode' => 'none', 'recipient_count' => 1]);
        $campaign->recipients()->create(['email' => 'synthetic@example.com', 'name' => 'Synthetic Recipient']);
        $campaign->forceFill(['dry_run_hash' => app(EmailCampaignPreflightService::class)->inspect($campaign)['hash']])->save();

        return $campaign;
    }

    private function frozen(): EmailCampaign
    {
        $campaign = $this->draft();
        app(EmailCampaignPreflightService::class)->freeze($campaign, $this->admin->id);
        $campaign->update(['status' => 'queued']);

        return $campaign;
    }

    private function confirmation(): array
    {
        return ['confirmed_mapping' => 1, 'confirmed_live_send' => 1];
    }

    private function smtp(bool $ready = true): void
    {
        $this->mock(EmailSmtpService::class, function ($mock) use ($ready): void {
            $mock->shouldReceive('isReady')->andReturn($ready);
            $mock->shouldReceive('sender')->andReturn(['sender@example.com', 'Synthetic Sender']);
            $mock->shouldReceive('activeSettings')->andReturnNull();
        });
    }

    private function worker(?string $problem = null): void
    {
        $this->mock(EmailCampaignQueueStatus::class)->shouldReceive('inspect')->andReturn(['connection' => 'email-database', 'queue' => 'email-campaigns', 'pending' => 0, 'problem' => $problem]);
    }
}
