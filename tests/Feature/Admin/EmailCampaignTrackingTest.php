<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Email\PostmarkWebhookController;
use App\Jobs\ProcessEmailProviderEvent;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailOptOut;
use App\Models\EmailProviderEvent;
use App\Models\EmailSmtpSetting;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\EmailTrackingToken;
use App\Models\User;
use App\Services\Email\CampaignMetrics;
use App\Services\Email\EmailTrackingService;
use App\Services\Email\PostmarkEventService;
use App\Services\Email\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailCampaignTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::post('/api/email/postmark', [PostmarkWebhookController::class, '__invoke']);
        config(['email_campaigns.postmark' => [
            'webhook_user' => 'fixture-user', 'webhook_password' => 'fixture-secret',
            'server_id' => 123, 'message_stream' => 'broadcast',
        ]]);
    }

    public function test_suppressions_preserve_marketing_opt_out_and_apply_complaints_to_every_purpose(): void
    {
        EmailOptOut::create(['email' => 'opted@example.com']);
        $service = app(SuppressionService::class);
        $this->assertTrue($service->blocked('OPTED@example.com', 'marketing'));
        $this->assertFalse($service->blocked('opted@example.com', 'transactional'));
        $service->suppress('other@example.com', 'complaint', 'all', 'postmark');
        $this->assertTrue($service->blocked('OTHER@example.com', 'transactional'));
        $this->assertTrue($service->blocked('other@example.com', 'payroll'));
    }

    public function test_smtp_metrics_are_unavailable_and_open_count_is_not_engagement(): void
    {
        [$campaign] = $this->fixture();
        $campaign->forceFill(['provider' => 'smtp', 'open_count' => 999])->save();
        $metrics = app(CampaignMetrics::class)->forCampaign($campaign);
        $this->assertNull($metrics['delivered']);
        $this->assertNull($metrics['opened']);
        $this->assertSame(0, $metrics['accepted']);
        $this->assertNull($metrics['delivery_rate']);
    }

    public function test_webhook_requires_https_auth_and_exact_identity(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $payload = $this->payload($recipient, $attempt);
        $this->postJson('/api/email/postmark', $payload)->assertStatus(403);
        $this->postJson('https://localhost/api/email/postmark', $payload)->assertUnauthorized();
        $payload['ServerID'] = 999;
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('email_provider_events', 0);
        $this->assertNull($recipient->fresh()->provider_delivered_at);
    }

    public function test_verified_receipts_are_minimal_durable_and_deduplicated_before_processing(): void
    {
        Queue::fake();
        [, $recipient, $attempt] = $this->fixture();
        $payload = [...$this->payload($recipient, $attempt), 'Details' => 'sensitive server response', 'Subject' => 'private subject'];
        $headers = [...$this->auth(), 'X-PM-Webhook-Trace-Id' => 'fixture-trace'];
        $this->withHeaders($headers)->postJson('https://localhost/api/email/postmark', $payload)->assertAccepted();
        $this->withHeaders($headers)->postJson('https://localhost/api/email/postmark', $payload)->assertAccepted();
        $this->assertDatabaseCount('email_provider_events', 1);
        Queue::assertPushed(ProcessEmailProviderEvent::class, 1);
        $event = EmailProviderEvent::firstOrFail();
        $serialized = json_encode($event->getAttributes());
        $this->assertStringNotContainsString($recipient->email, $serialized);
        $this->assertStringNotContainsString('private subject', $serialized);
        $this->assertNull($recipient->fresh()->provider_delivered_at);
    }

    public function test_early_delivery_proves_acceptance_and_is_monotonic_under_replayed_processing(): void
    {
        Queue::fake();
        [$campaign, $recipient, $attempt] = $this->fixture();
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $this->payload($recipient, $attempt))->assertAccepted();
        $event = EmailProviderEvent::firstOrFail();
        app(PostmarkEventService::class)->process($event->id);
        app(PostmarkEventService::class)->process($event->id);
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertNotNull($recipient->fresh()->provider_delivered_at);
        $this->assertSame('accepted', DB::table('email_campaign_attempts')->where('id', $attempt)->value('status'));
        $metrics = app(CampaignMetrics::class)->forCampaign($campaign->fresh());
        $this->assertSame(1, $metrics['accepted']);
        $this->assertSame(1, $metrics['delivered']);
        $this->assertSame(100.0, $metrics['delivery_rate']);
    }

    public function test_wrong_recipient_stream_or_unentered_attempt_cannot_change_delivery(): void
    {
        [, $recipient, $attempt] = $this->fixture();
        foreach ([['Recipient' => 'attacker@example.com'], ['MessageStream' => 'outbound'], ['Metadata' => ['attempt' => (string) Str::uuid()]]] as $wrong) {
            $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', array_replace($this->payload($recipient, $attempt), $wrong))->assertUnprocessable();
        }
        DB::table('email_campaign_attempts')->where('id', $attempt)->update(['entered_transport_at' => null]);
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $this->payload($recipient, $attempt))->assertUnprocessable();
        $this->assertDatabaseCount('email_provider_events', 0);
    }

    public function test_hard_bounce_and_complaint_suppress_while_soft_bounce_only_records_feedback(): void
    {
        Queue::fake();
        foreach (['SoftBounce', 'HardBounce', 'SpamComplaint'] as $type) {
            [, $recipient, $attempt] = $this->fixture();
            $payload = $this->payload($recipient, $attempt);
            $payload['RecordType'] = $type === 'SpamComplaint' ? 'SpamComplaint' : 'Bounce';
            $payload['Type'] = $type;
            $payload['Email'] = $payload['Recipient'];
            $payload['BouncedAt'] = $payload['DeliveredAt'];
            $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $payload)->assertAccepted();
            app(PostmarkEventService::class)->process(EmailProviderEvent::latest('id')->firstOrFail()->id);
            $this->assertSame($type !== 'SoftBounce', app(SuppressionService::class)->blocked($recipient->email, 'transactional'));
        }
    }

    public function test_tracking_is_default_off_and_never_enabled_for_confidential_delivery(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $html = '<p>Hello <a href="https://example.com/news">News</a></p>';
        $this->assertSame($html, app(EmailTrackingService::class)->decorate($html, $campaign, $recipient, $attempt));
        $campaign->forceFill(['tracking_opens' => true, 'tracking_clicks' => true, 'purpose' => 'payroll'])->save();
        $this->assertSame($html, app(EmailTrackingService::class)->decorate($html, $campaign, $recipient, $attempt));
        $this->assertDatabaseCount('email_tracking_tokens', 0);
    }

    public function test_opaque_clicks_have_frozen_destinations_and_unsubscribe_get_does_not_opt_out(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $campaign->forceFill(['tracking_opens' => true, 'tracking_clicks' => true])->save();
        $html = app(EmailTrackingService::class)->decorate('<a href="https://example.com/news">News</a>', $campaign, $recipient, $attempt);
        $this->assertStringNotContainsString($recipient->email, $html);
        preg_match('/href="([^"]+)"/', $html, $match);
        $url = html_entity_decode($match[1]);
        $this->get($url.'?url=https://evil.example')->assertRedirect('https://example.com/news');
        $this->get($url)->assertRedirect('https://example.com/news');
        $this->assertNotNull($recipient->fresh()->observed_clicked_at);
        $unsubscribe = app(EmailTrackingService::class)->unsubscribeUrl($campaign, $recipient);
        $this->get($unsubscribe)->assertOk();
        $this->assertFalse(app(SuppressionService::class)->blocked($recipient->email, 'marketing'));
        $this->post($unsubscribe, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertTrue(app(SuppressionService::class)->blocked($recipient->email, 'marketing'));
        $this->assertNotNull($recipient->fresh()->unsubscribed_at);
        $this->assertSame(1, app(CampaignMetrics::class)->forCampaign($campaign)['clicked']);
    }

    public function test_tracking_respects_privacy_signals_and_rejects_unknown_tokens(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $campaign->forceFill(['tracking_opens' => true])->save();
        $html = app(EmailTrackingService::class)->decorate('<p>hello</p>', $campaign, $recipient, $attempt);
        preg_match('/src="([^"]+)"/', $html, $match);
        $this->withHeaders(['DNT' => '1'])->get(html_entity_decode($match[1]))->assertOk();
        $this->assertNull($recipient->fresh()->observed_opened_at);
        $this->get(route('email-tracking.click', str_repeat('a', 64)))->assertNotFound();
    }

    public function test_frozen_tracking_settings_do_not_fall_back_to_mutable_campaign_flags(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $campaign->forceFill(['tracking_opens' => true, 'tracking_clicks' => true, 'delivery_snapshot' => [
            'purpose' => 'marketing', 'attachment_mode' => 'none', 'template' => ['pdf_enabled' => false], 'provider' => 'smtp',
        ]])->save();
        $html = '<p>Approved legacy content</p>';
        $this->assertSame($html, app(EmailTrackingService::class)->decorate($html, $campaign, $recipient, $attempt));
        $this->assertDatabaseCount('email_tracking_tokens', 0);
    }

    public function test_postmark_delivery_metrics_are_unavailable_without_callback_configuration(): void
    {
        [$campaign] = $this->fixture();
        config(['email_campaigns.postmark.webhook_password' => '']);
        $metrics = app(CampaignMetrics::class)->forCampaign($campaign);
        $this->assertFalse($metrics['provider_coverage']);
        $this->assertNull($metrics['delivered']);
    }

    public function test_authenticated_tracking_visits_do_not_audit_raw_tokens_or_request_identity(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $campaign->forceFill(['tracking_opens' => true])->save();
        $html = app(EmailTrackingService::class)->decorate('<p>Hello</p>', $campaign, $recipient, $attempt);
        preg_match('/src="([^"]+)"/', $html, $match);
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $before = \Spatie\Activitylog\Models\Activity::where('log_name', 'request')->count();
        $this->actingAs($actor)->get(html_entity_decode($match[1]), ['User-Agent' => 'private-fixture-agent'])->assertOk();
        $this->assertSame($before, \Spatie\Activitylog\Models\Activity::where('log_name', 'request')->count());
        $this->assertStringNotContainsString('private-fixture-agent', json_encode(EmailTrackingToken::firstOrFail()->getAttributes()));
    }

    public function test_provider_identity_must_match_the_frozen_account_and_stream(): void
    {
        [$campaign, $recipient, $attempt] = $this->fixture();
        $campaign->forceFill(['delivery_snapshot' => ['provider' => 'postmark', 'postmark_server_id' => 999, 'postmark_message_stream' => 'broadcast']])->save();
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $this->payload($recipient, $attempt))->assertUnprocessable();
        $this->assertDatabaseCount('email_provider_events', 0);
    }

    public function test_expired_tokens_fail_closed_and_recovery_preserves_suppressions_and_unsubscribe_tokens(): void
    {
        Queue::fake();
        [$campaign, $recipient, $attempt] = $this->fixture();
        $campaign->forceFill(['tracking_opens' => true])->save();
        $tracking = app(EmailTrackingService::class);
        $html = $tracking->decorate('<p>Fixture</p>', $campaign, $recipient, $attempt);
        preg_match('/src="([^"]+)"/', $html, $match);
        EmailTrackingToken::where('kind', 'open')->update(['expires_at' => now()->subMinute()]);
        $this->get(html_entity_decode($match[1]))->assertStatus(410);
        $unsubscribe = $tracking->unsubscribeUrl($campaign, $recipient);
        app(SuppressionService::class)->suppress($recipient->email, 'complaint', 'all', 'postmark');
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $this->payload($recipient, $attempt))->assertAccepted();
        EmailProviderEvent::query()->update(['created_at' => now()->subMinutes(3)]);
        $this->artisan('email-feedback:maintain')->assertSuccessful();
        Queue::assertPushed(ProcessEmailProviderEvent::class, 2);
        $this->assertDatabaseCount('email_suppressions', 1);
        $this->assertDatabaseCount('email_tracking_tokens', 1);
        $this->get($unsubscribe)->assertOk();
    }

    public function test_conflicting_trace_and_provider_message_id_are_rejected(): void
    {
        Queue::fake();
        [, $recipient, $attempt] = $this->fixture();
        $payload = $this->payload($recipient, $attempt);
        $headers = [...$this->auth(), 'X-PM-Webhook-Trace-Id' => 'one-trace'];
        $this->withHeaders($headers)->postJson('https://localhost/api/email/postmark', $payload)->assertAccepted();
        $payload['RecordType'] = 'Bounce';
        $payload['Email'] = $recipient->email;
        $payload['BouncedAt'] = $payload['DeliveredAt'];
        $payload['Type'] = 'HardBounce';
        $this->withHeaders($headers)->postJson('https://localhost/api/email/postmark', $payload)->assertStatus(409);
        app(PostmarkEventService::class)->process(EmailProviderEvent::firstOrFail()->id);
        $payload['MessageID'] = (string) Str::uuid();
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('email_provider_events', 1);
        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_only_super_admin_can_add_or_remove_manual_suppressions_and_provider_blocks_are_protected(): void
    {
        Route::post('/_tracking/suppressions', [\App\Http\Controllers\Admin\EmailSuppressionsController::class, 'store']);
        Route::delete('/_tracking/suppressions/{suppression}', [\App\Http\Controllers\Admin\EmailSuppressionsController::class, 'destroy'])->middleware('web');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post('/_tracking/suppressions', ['email' => 'blocked@example.com', 'purpose' => 'all'])->assertForbidden();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->post('/_tracking/suppressions', ['email' => 'blocked@example.com', 'purpose' => 'all'])->assertRedirect();
        $manual = EmailSuppression::firstOrFail();
        $this->actingAs($superAdmin)->delete('/_tracking/suppressions/'.$manual->id)->assertRedirect();
        $protected = app(SuppressionService::class)->suppress('blocked@example.com', 'complaint', 'all', 'postmark');
        $this->actingAs($superAdmin)->delete('/_tracking/suppressions/'.$protected->id)->assertStatus(409);
        $this->assertDatabaseCount('email_suppressions', 1);
    }

    public function test_super_admin_provider_credentials_are_encrypted_hidden_and_blank_preserves_secret(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $payload = $this->providerSettings();
        $this->actingAs($actor)->put(route('admin.email-configuration.update'), $payload)->assertSessionHasNoErrors();
        $setting = EmailSmtpSetting::firstOrFail();
        $this->assertSame('postmark', $setting->provider);
        $this->assertSame($payload['postmark_webhook_password'], $setting->postmark_webhook_password);
        $this->assertNotSame($payload['postmark_webhook_password'], $setting->getRawOriginal('postmark_webhook_password'));
        $this->assertArrayNotHasKey('postmark_webhook_password', $setting->toArray());
        $this->get(route('admin.email-configuration.index'))->assertOk()->assertDontSee($payload['postmark_webhook_password']);
        $payload['postmark_webhook_password'] = '';
        $this->put(route('admin.email-configuration.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('callback-fixture-password', $setting->fresh()->postmark_webhook_password);
        $this->assertSame('callback-fixture-password', app(PostmarkEventService::class)->configuration()['webhook_password']);
    }

    public function test_invalid_provider_settings_do_not_flash_webhook_credentials(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $payload = $this->providerSettings();
        $payload['port'] = 70000;
        $response = $this->actingAs($actor)->from(route('admin.email-configuration.index'))->put(route('admin.email-configuration.update'), $payload);
        $response->assertSessionHasErrors('port');
        $this->assertArrayNotHasKey('postmark_webhook_password', session('_old_input', []));
        $this->assertDatabaseCount('email_smtp_settings', 0);
    }

    public function test_late_provider_acceptance_prevents_claim_of_a_revalidated_pending_recipient(): void
    {
        Queue::fake();
        [$campaign, $recipient, $attempt] = $this->fixture();
        $recipient->forceFill(['status' => 'pending'])->save();
        $this->withHeaders($this->auth())->postJson('https://localhost/api/email/postmark', $this->payload($recipient, $attempt))->assertAccepted();
        app(PostmarkEventService::class)->process(EmailProviderEvent::firstOrFail()->id);
        $this->assertNull(app(\App\Services\EmailCampaignRecipientTransitionService::class)->claim($campaign->id, $recipient->id, 0));
        $this->assertSame('accepted', $recipient->fresh()->status);
    }

    public function test_provider_acceptance_survives_a_lost_smtp_return_in_the_managed_sender(): void
    {
        Queue::fake();
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        EmailSmtpSetting::create($this->providerSettings());
        $smtp = \Mockery::mock(\App\Services\EmailSmtpService::class)->makePartial();
        $smtp->shouldReceive('transportConfiguration')->andReturn(['transport' => 'array']);
        $this->app->instance(\App\Services\EmailSmtpService::class, $smtp);
        [$campaign, $recipient] = $this->fixture();
        DB::table('email_campaign_attempts')->where('email_campaign_id', $campaign->id)->delete();
        $campaign->forceFill(['status' => 'draft', 'generation' => 1])->save();
        $recipient->forceFill(['status' => 'pending', 'consent_status' => 'asserted', 'consent_source' => 'Fixture permission', 'consent_asserted_at' => now()])->save();
        app(\App\Services\Email\CampaignApproval::class)->freeze($campaign->fresh(), $actor->id);
        $campaign->forceFill(['status' => 'queued'])->save();
        $mailer = \Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('html')->once()->andReturnUsing(function () use ($campaign, $recipient): void {
            $attempt = DB::table('email_campaign_attempts')->where('email_campaign_id', $campaign->id)->first();
            $payload = $this->payload($recipient, $attempt->id);
            $request = \Illuminate\Http\Request::create('https://localhost/api/email/postmark', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('fixture-user:callback-fixture-password'),
            ], json_encode($payload));
            $events = app(PostmarkEventService::class);
            $events->process($events->receive($request)->id);

            throw new \RuntimeException('Synthetic SMTP return lost after verified delivery event.');
        });
        \Illuminate\Support\Facades\Mail::shouldReceive('build')->once()->andReturn($mailer);
        app(\App\Services\Email\ManagedEmailDelivery::class)->send($campaign->id, $recipient->id, 1);
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertNotNull($recipient->fresh()->provider_delivered_at);
        $this->assertSame('accepted', DB::table('email_campaign_attempts')->where('email_campaign_id', $campaign->id)->value('status'));
        $this->assertNull($recipient->fresh()->error);
    }

    private function providerSettings(): array
    {
        return ['enabled' => true, 'host' => 'smtp-broadcasts.postmarkapp.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'fixture-smtp-user', 'password' => 'fixture-smtp-password', 'from_name' => 'Fixture sender', 'from_address' => 'fixture@example.com',
            'provider' => 'postmark', 'rate_per_minute' => 30, 'postmark_webhook_user' => 'fixture-user',
            'postmark_webhook_password' => 'callback-fixture-password', 'postmark_server_id' => 123, 'postmark_message_stream' => 'broadcast'];
    }

    private function fixture(): array
    {
        $template = EmailTemplate::create(['name' => 'Fixture', 'subject' => 'Hello', 'html_body' => 'Hi']);
        $campaign = EmailCampaign::create(['email_template_id' => $template->id, 'name' => 'Fixture', 'status' => 'queued', 'recipient_count' => 1]);
        $campaign->forceFill(['provider' => 'postmark', 'management_version' => 3, 'open_count' => 1, 'attention_count' => 0])->save();
        $campaign->refresh();
        $recipient = $campaign->recipients()->create(['email' => Str::random(8).'@example.com', 'name' => 'Fixture', 'status' => 'sending']);
        $attempt = (string) Str::uuid();
        DB::table('email_campaign_attempts')->insert(['id' => $attempt, 'email_campaign_id' => $campaign->id, 'email_campaign_recipient_id' => $recipient->id, 'generation' => 0, 'status' => 'submitting', 'provider' => 'postmark', 'entered_transport_at' => now()->subSeconds(2), 'created_at' => now(), 'updated_at' => now()]);

        return [$campaign, $recipient, $attempt];
    }

    private function payload(EmailCampaignRecipient $recipient, string $attempt): array
    {
        return ['RecordType' => 'Delivery', 'ServerID' => 123, 'MessageStream' => 'broadcast', 'MessageID' => (string) Str::uuid(), 'Recipient' => $recipient->email, 'DeliveredAt' => now()->toIso8601String(), 'Metadata' => ['attempt' => $attempt]];
    }

    private function auth(): array
    {
        return ['Authorization' => 'Basic '.base64_encode('fixture-user:fixture-secret')];
    }
}
