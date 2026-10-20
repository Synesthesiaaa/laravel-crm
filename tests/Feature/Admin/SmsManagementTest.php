<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendSmsMessage;
use App\Models\SmsCampaign;
use App\Models\SmsChannel;
use App\Models\SmsConsent;
use App\Models\SmsGateway;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\User;
use App\Services\Sms\GoipHttpTransport;
use App\Services\Sms\GoipReceiver;
use App\Services\Sms\SmsCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SmsGateway $gateway;

    private SmsChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00', 'Asia/Manila')->utc());
        config(['sms.allowed_ips' => ['192.168.10.20'], 'sms.live_enabled' => true, 'sms.emergency_stop' => false, 'sms.queue_connection' => 'database']);
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->gateway = SmsGateway::create(['name' => 'Test gateway', 'host' => '192.168.10.20', 'port' => 80, 'username' => 'fixture', 'password' => 'fixture-secret', 'firmware' => 'GST1610-1.01-70-17-8', 'enabled' => true, 'verified_at' => now(), 'inbound_verified' => true]);
        $this->channel = $this->gateway->channels()->create(['number' => 1, 'client_id' => 'fixture-client', 'password' => 'fixture-udp', 'enabled' => true, 'interval_seconds' => 30, 'daily_limit' => 100]);
    }

    private function campaign(string $status = 'draft'): SmsCampaign
    {
        return SmsCampaign::create(['name' => 'Appointment reminders', 'created_by' => $this->admin->id, 'sms_gateway_id' => $this->gateway->id, 'category' => 'operational', 'body' => 'Hello {{recipient_name}}', 'status' => $status, 'channel_ids' => [$this->channel->id], 'quiet_start' => 20, 'quiet_end' => 8]);
    }

    private function recipient(SmsCampaign $campaign, string $status = 'eligible'): SmsMessage
    {
        $consent = SmsConsent::firstOrCreate(['phone' => '+639171234567', 'category' => $campaign->category], ['source' => 'Fixture opt-in form', 'consented_at' => now()->subDay(), 'recorded_by' => $this->admin->id]);

        return $campaign->messages()->create(['phone' => $consent->phone, 'recipient_name' => 'Fixture recipient', 'sms_consent_id' => $consent->id, 'body' => 'Appointment reminder', 'status' => $status, 'idempotency_key' => (string) Str::uuid()]);
    }

    public function test_all_sms_pages_require_super_admin_and_navigation_is_hidden(): void
    {
        $this->get(route('admin.sms.index'))->assertRedirect(route('login'));
        foreach ([User::ROLE_ADMIN, User::ROLE_TEAM_LEADER, User::ROLE_AGENT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            foreach (['overview', 'campaigns', 'history', 'templates', 'gateways', 'reports'] as $workspace) {
                $this->get(route('admin.sms.index', ['workspace' => $workspace]))->assertForbidden();
            }
            $this->post(route('admin.sms.store'), [])->assertForbidden();
            $this->post(route('admin.sms.gateways.store'), [])->assertForbidden();
            $this->get(route('admin.sms.report'))->assertForbidden();
            $routes = collect(\App\Support\AdminNavigation::workspaceGroups($user, true))->pluck('items')->flatten(1)->pluck('route');
            $this->assertNotContains('admin.sms.index', $routes);
        }
    }

    public function test_super_admin_pages_render_with_honest_empty_states(): void
    {
        $this->actingAs($this->admin);
        foreach (['overview', 'campaigns', 'history', 'templates', 'gateways', 'reports'] as $workspace) {
            $this->get(route('admin.sms.index', ['workspace' => $workspace]))->assertOk()->assertDontSee('fixture-secret')->assertDontSee('fixture-udp');
        }
        $this->get(route('admin.sms.review', $this->campaign()))->assertOk()->assertSee('No recipients');
    }

    public function test_gateway_passwords_are_encrypted_hidden_and_blank_replacement_preserves(): void
    {
        $this->assertNotSame('fixture-secret', DB::table('sms_gateways')->value('password'));
        $this->assertArrayNotHasKey('password', $this->gateway->toArray());
        $this->assertNotSame('fixture-udp', DB::table('sms_channels')->value('password'));
        $this->actingAs($this->admin)->put(route('admin.sms.gateways.update', $this->gateway), ['name' => 'Updated', 'host' => $this->gateway->host, 'port' => 80, 'username' => 'fixture', 'password' => '', 'firmware' => $this->gateway->firmware])->assertSessionHasNoErrors();
        $this->assertSame('fixture-secret', $this->gateway->fresh()->password);
        $this->assertNull($this->gateway->fresh()->verified_at);
        Http::assertNothingSent();
    }

    public function test_gateway_arbitrary_destinations_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.sms.gateways.store'), ['name' => 'Unsafe', 'host' => '169.254.169.254', 'port' => 80, 'username' => 'x', 'password' => 'x', 'firmware' => 'x'])->assertSessionHasErrors('host');
        Http::assertNothingSent();
    }

    public function test_import_normalizes_deduplicates_and_requires_real_evidence(): void
    {
        $campaign = $this->campaign();
        SmsOptOut::create(['phone' => '+639181234567', 'source' => 'fixture']);
        $csv = "phone,recipient_name,consent_source,consented_at\n09171234567,A,Fixture opt-in,2026-01-01T00:00:00+08:00\n+639171234567,A,,\ninvalid,B,,\n09191234567,C,,\n";
        $this->actingAs($this->admin)->post(route('admin.sms.import', $campaign), ['recipients' => UploadedFile::fake()->createWithContent('recipients.csv', $csv), 'confirm_evidence' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(['duplicate' => 1, 'eligible' => 1, 'invalid' => 1, 'no_consent' => 1], app(SmsCampaignService::class)->preview($campaign->fresh())['counts']);
    }

    public function test_malformed_import_rolls_back_all_rows(): void
    {
        $campaign = $this->campaign();
        $csv = "phone,recipient_name,consent_source,consented_at\n09171234567,A,,\n09181234567,extra,columns,,,\n";
        $this->actingAs($this->admin)->post(route('admin.sms.import', $campaign), ['recipients' => UploadedFile::fake()->createWithContent('recipients.csv', $csv), 'confirm_evidence' => 1])->assertSessionHasErrors('recipients');
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_recipient_evaluation_rejects_suppression_and_unsupported_content(): void
    {
        $campaign = $this->campaign();
        $this->recipient($campaign);
        SmsOptOut::create(['phone' => '+639181234567', 'source' => 'fixture']);
        $campaign->update(['category' => 'marketing']);
        $service = app(SmsCampaignService::class);
        $service->addRecipient($campaign, ['phone' => '09181234567'], $this->admin);
        $this->assertDatabaseHas('sms_messages', ['phone' => '+639181234567', 'status' => 'suppressed']);
        $campaign->update(['body' => 'Unicode 😀']);
        $service->addRecipient($campaign, ['phone' => '09191234567'], $this->admin);
        $this->assertDatabaseHas('sms_messages', ['phone' => '+639191234567', 'status' => 'unsupported']);
    }

    public function test_confirmation_binds_audience_and_queues_no_synchronous_sms(): void
    {
        $campaign = $this->campaign();
        $message = $this->recipient($campaign);
        $digest = app(SmsCampaignService::class)->preview($campaign)['digest'];
        $this->actingAs($this->admin)->post(route('admin.sms.confirm', $campaign), ['digest' => $digest, 'confirm_audience' => 1])->assertSessionHasNoErrors();
        $this->assertSame('queued', $message->fresh()->status);
        $this->assertSame('queued', $campaign->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_opt_out_invalidates_audience_confirmation(): void
    {
        $campaign = $this->campaign();
        $this->recipient($campaign);
        $digest = app(SmsCampaignService::class)->preview($campaign)['digest'];
        SmsOptOut::create(['phone' => '+639171234567', 'source' => 'fixture']);
        // Operational consent is separate; revocation invalidates operational audience too.
        SmsConsent::query()->update(['revoked_at' => now()]);
        $this->actingAs($this->admin)->post(route('admin.sms.confirm', $campaign), ['digest' => $digest, 'confirm_audience' => 1])->assertSessionHasErrors('audience');
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_live_disabled_blocks_confirmation(): void
    {
        config(['sms.live_enabled' => false]);
        $campaign = $this->campaign();
        $this->recipient($campaign);
        $digest = app(SmsCampaignService::class)->preview($campaign)['digest'];
        $this->actingAs($this->admin)->post(route('admin.sms.confirm', $campaign), ['digest' => $digest, 'confirm_audience' => 1])->assertSessionHasErrors('gateway');
        Http::assertNothingSent();
    }

    public function test_job_submits_once_and_never_calls_acceptance_delivered(): void
    {
        Http::fake(['*' => Http::response('Sending,L1 Send SMS to:+639171234567; ID:0000021f', 200)]);
        $campaign = $this->campaign('queued');
        $message = $this->recipient($campaign, 'queued');
        $job = new SendSmsMessage($message->id);
        $job->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        $job->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        Http::assertSentCount(1);
        $this->assertSame('submitted', $message->fresh()->status);
        $this->assertNull($message->fresh()->delivered_at);
        $this->assertSame('0000021f', $message->fresh()->gateway_message_id);
    }

    public function test_ambiguous_outcomes_are_not_retried_and_disable_channel(): void
    {
        Http::fake(['*' => Http::response('unexpected response', 200)]);
        $message = $this->recipient($this->campaign('queued'), 'queued');
        $job = new SendSmsMessage($message->id);
        $job->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        $job->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        Http::assertSentCount(1);
        $this->assertSame('unknown', $message->fresh()->status);
        $this->assertFalse($this->channel->fresh()->enabled);
    }

    public function test_pause_cancel_and_role_revocation_prevent_submission(): void
    {
        $campaign = $this->campaign('queued');
        $message = $this->recipient($campaign, 'queued');
        $service = app(SmsCampaignService::class);
        $service->transition($campaign, 'pause', $this->admin);
        (new SendSmsMessage($message->id))->handle($service, app(GoipHttpTransport::class));
        Http::assertNothingSent();
        $service->transition($campaign, 'resume', $this->admin);
        $service->transition($campaign, 'cancel', $this->admin);
        $this->assertSame('canceled', $message->fresh()->status);
        $this->actingAs($this->admin)->post(route('admin.sms.transition', [$campaign, 'resume']))->assertStatus(409);
    }

    public function test_revoked_consent_and_quiet_hours_prevent_transport_io(): void
    {
        $campaign = $this->campaign('queued');
        $message = $this->recipient($campaign, 'queued');
        $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(23, 0)->utc());
        (new SendSmsMessage($message->id))->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        $this->assertSame('queued', $message->fresh()->status);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00', 'Asia/Manila')->utc());
        SmsConsent::query()->update(['revoked_at' => now()]);
        (new SendSmsMessage($message->id))->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        $this->assertSame('canceled', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_maintenance_recovers_processing_as_unknown_without_resend(): void
    {
        Queue::fake();
        $message = $this->recipient($this->campaign('running'), 'processing');
        $message->update(['sms_channel_id' => $this->channel->id, 'processing_at' => now()->subMinutes(6)]);
        $this->artisan('sms:maintain')->assertSuccessful();
        $this->assertSame('unknown', $message->fresh()->status);
        $this->assertFalse($this->channel->fresh()->enabled);
        Queue::assertNothingPushed();
    }

    public function test_receiver_authenticates_deduplicates_and_applies_stop(): void
    {
        $receiver = app(GoipReceiver::class);
        $packet = 'RECEIVE:41;id:fixture-client;pass:fixture-udp;srcnum:+639171234567;msg:STOP';
        $campaign = $this->campaign('queued');
        $campaign->update(['category' => 'marketing']);
        $message = $this->recipient($campaign, 'queued');
        $this->assertNull($receiver->receive($packet, '192.168.10.99'));
        $this->assertNull($receiver->receive(str_replace('fixture-udp', 'wrong', $packet), '192.168.10.20'));
        $this->assertNull($receiver->receive(str_replace('fixture-client', 'FIXTURE-CLIENT', $packet), '192.168.10.20'));
        $this->assertNull($receiver->receive('req:99;'.$packet, '192.168.10.20'));
        $this->assertSame('RECEIVE 41 OK', $receiver->receive($packet, '192.168.10.20'));
        $this->assertSame('RECEIVE 41 OK', $receiver->receive($packet, '192.168.10.20'));
        $this->assertSame('canceled', $message->fresh()->status);
        $this->assertDatabaseCount('sms_gateway_events', 1);
        $this->assertSame(1, SmsMessage::where('direction', 'inbound')->count());
        $this->assertDatabaseHas('sms_opt_outs', ['phone' => '+639171234567']);
        $this->assertNull($receiver->receive(str_replace('STOP', 'START', $packet), '192.168.10.20'));
    }

    public function test_receiver_preserves_message_delimiters_rejects_oversize_and_unknown_delivery(): void
    {
        $receiver = app(GoipReceiver::class);
        $this->assertSame('a;b:c', $receiver->parse('RECEIVE:1;msg:a;b:c')['msg']);
        $this->assertNull($receiver->receive(str_repeat('x', 4097), '192.168.10.20'));
        $this->assertNull($receiver->receive('DELIVER:1;id:fixture-client;pass:fixture-udp;state:0', '192.168.10.20'));
        $this->assertSame('reg:42;status:0;', $receiver->receive('req:42;id:fixture-client;pass:fixture-udp', '192.168.10.20'));
    }

    public function test_reference_http_response_requires_matching_channel_and_number(): void
    {
        $transport = app(GoipHttpTransport::class);
        $response = 'Sending,L1 Send SMS to:+639171234567; ID:0000021f';
        $this->assertSame('submitted', $transport->interpret(200, $response, 1, '+639171234567')['status']);
        $this->assertSame('unknown', $transport->interpret(200, $response, 2, '+639171234567')['status']);
        $this->assertSame('unknown', $transport->interpret(200, $response, 1, '+639181234567')['status']);
        $this->assertSame('failed', $transport->interpret(401, '', 1, '+639171234567')['status']);
        $this->assertSame('unknown', $transport->interpret(200, 'DONE', 1, '+639171234567')['status']);
    }

    public function test_export_is_authorized_audited_and_formula_safe(): void
    {
        $this->recipient($this->campaign());
        $response = $this->actingAs($this->admin)->get(route('admin.sms.report'));
        $response->assertOk();
        $this->assertStringContainsString("'+639171234567", $response->streamedContent());
        $this->assertStringNotContainsString('Appointment reminder', $response->streamedContent());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'sms', 'description' => 'SMS report exported']);
    }

    public function test_scheduling_channel_limits_and_role_loss_block_transport(): void
    {
        $campaign = $this->campaign('scheduled');
        $campaign->update(['scheduled_at' => now()->addDay()]);
        $message = $this->recipient($campaign, 'queued');
        $run = fn () => (new SendSmsMessage($message->id))->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        $run();
        $this->assertSame('queued', $message->fresh()->status);
        $campaign->update(['scheduled_at' => null]);
        $this->channel->update(['usage_date' => now('Asia/Manila')->toDateString(), 'used_today' => 100]);
        $run();
        $this->assertSame('queued', $message->fresh()->status);
        $this->admin->update(['role' => User::ROLE_ADMIN]);
        $run();
        $this->assertSame('failed', $campaign->fresh()->status);
        $this->assertSame('canceled', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_emergency_stop_keeps_authenticated_inbound_stop_available(): void
    {
        $campaign = $this->campaign('queued');
        $this->actingAs($this->admin)->post(route('admin.sms.emergency-stop'))->assertSessionHasNoErrors();
        $this->assertFalse($this->gateway->fresh()->enabled);
        $this->assertSame('paused', $campaign->fresh()->status);
        $ack = app(GoipReceiver::class)->receive('RECEIVE:77;id:fixture-client;pass:fixture-udp;srcnum:+639171234567;msg:STOP', '192.168.10.20');
        $this->assertSame('RECEIVE 77 OK', $ack);
        $this->assertDatabaseHas('sms_opt_outs', ['phone' => '+639171234567']);
    }

    public function test_retention_requires_opt_in_and_preserves_idempotency(): void
    {
        $message = $this->recipient($this->campaign('completed'), 'submitted');
        $message->update(['created_at' => now()->subDays(40)]);
        config(['sms.retention_days' => 0]);
        $this->artisan('sms:prune')->assertSuccessful();
        $this->assertNotNull($message->fresh()->body);
        config(['sms.retention_days' => 30]);
        $this->artisan('sms:prune')->assertSuccessful();
        $this->assertNull($message->fresh()->body);
        $this->assertSame('submitted', $message->fresh()->status);
        $this->assertSame($message->idempotency_key, $message->fresh()->idempotency_key);
    }

    public function test_designated_live_test_is_explicit_and_uses_only_configured_number(): void
    {
        $campaign = $this->campaign();
        $this->recipient($campaign);
        config(['sms.test_number' => '+639171234567']);
        $this->actingAs($this->admin)->post(route('admin.sms.test', $campaign), [])->assertSessionHasErrors('confirm_test');
        $this->post(route('admin.sms.test', $campaign), ['confirm_test' => 1])->assertSessionHasNoErrors();
        $test = SmsCampaign::where('id', '!=', $campaign->id)->firstOrFail();
        $this->assertSame('queued', $test->status);
        $this->assertSame('+639171234567', $test->messages()->sole()->phone);
        Http::assertNothingSent();
    }

    public function test_draft_modification_invalidates_confirmation_and_cannot_change_sent_campaigns(): void
    {
        $campaign = $this->campaign();
        $payload = ['name' => 'Updated draft', 'body' => 'New reminder', 'category' => 'operational', 'sms_gateway_id' => $this->gateway->id, 'channel_ids' => [$this->channel->id], 'quiet_start' => 20, 'quiet_end' => 8];
        $this->actingAs($this->admin)->put(route('admin.sms.update', $campaign), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Updated draft', $campaign->fresh()->name);
        $this->recipient($campaign);
        $this->put(route('admin.sms.update', $campaign), $payload)->assertStatus(409);
        $this->post(route('admin.sms.reset-audience', $campaign), ['confirm_reset' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $campaign->messages()->count());
        $this->assertDatabaseCount('sms_consents', 1);
        $campaign->update(['status' => 'completed']);
        $this->put(route('admin.sms.update', $campaign), $payload)->assertStatus(409);
    }

    public function test_sms_request_audit_omits_recipient_search_values(): void
    {
        $this->actingAs($this->admin)->get(route('admin.sms.index', ['workspace' => 'history', 'phone' => '+639171234567']))->assertOk();
        $properties = DB::table('activity_log')->where('log_name', 'request')->pluck('properties')->implode('');
        $this->assertStringNotContainsString('639171234567', $properties);
    }

    public function test_schedule_validation_uses_manila_instead_of_server_wall_time(): void
    {
        $payload = ['name' => 'Scheduled reminder', 'body' => 'Reminder', 'category' => 'operational', 'sms_gateway_id' => $this->gateway->id, 'channel_ids' => [$this->channel->id], 'quiet_start' => 20, 'quiet_end' => 8, 'scheduled_at' => '2026-10-20T11:59'];
        $this->actingAs($this->admin)->post(route('admin.sms.store'), $payload)->assertSessionHasErrors('scheduled_at');
        $payload['scheduled_at'] = '2026-10-20T12:30';
        $this->post(route('admin.sms.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 04:30', SmsCampaign::latest('id')->firstOrFail()->scheduled_at->utc()->format('Y-m-d H:i'));
    }

    public function test_timeout_is_sanitized_and_never_blindly_retried(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('secret fixture password and SMS content'));
        $message = $this->recipient($this->campaign('queued'), 'queued');
        (new SendSmsMessage($message->id))->handle(app(SmsCampaignService::class), app(GoipHttpTransport::class));
        $this->assertSame('unknown', $message->fresh()->status);
        $this->assertSame('transport_outcome_unknown', $message->fresh()->reason);
        $this->assertStringNotContainsString('secret fixture', DB::table('sms_gateway_events')->get()->toJson());
        Queue::fake();
        $this->artisan('sms:maintain')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_unknown_reconciliation_never_sends_and_requires_evidence(): void
    {
        $message = $this->recipient($this->campaign('completed'), 'unknown');
        $this->actingAs($this->admin)->post(route('admin.sms.reconcile', $message), ['outcome' => 'submitted', 'evidence' => 'short'])->assertSessionHasErrors('evidence');
        $this->post(route('admin.sms.reconcile', $message), ['outcome' => 'submitted', 'evidence' => 'Incident ticket SMS-FIXTURE-001 confirms gateway acceptance'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $message->fresh()->status);
        $this->assertNull($message->fresh()->delivered_at);
        $this->post(route('admin.sms.reconcile', $message), ['outcome' => 'failed', 'evidence' => 'Incident ticket SMS-FIXTURE-001 rejected duplicate reconciliation'])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_inbound_persistence_failure_rolls_back_without_acknowledging(): void
    {
        \App\Models\SmsGatewayEvent::creating(static fn () => throw new \RuntimeException('Fixture storage failure'));

        try {
            app(GoipReceiver::class)->receive('RECEIVE:93;id:fixture-client;pass:fixture-udp;srcnum:+639171234567;msg:Hello', '192.168.10.20');
            $this->fail('An acknowledgment must not be returned after persistence failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fixture storage failure', $exception->getMessage());
            $this->assertDatabaseCount('sms_messages', 0);
            $this->assertDatabaseCount('sms_gateway_events', 0);
        } finally {
            \App\Models\SmsGatewayEvent::flushEventListeners();
        }
    }

    public function test_history_phone_filter_stays_out_of_redirect_urls(): void
    {
        $this->actingAs($this->admin)->post(route('admin.sms.history-filter'), ['phone' => '09171234567', 'direction' => 'inbound'])
            ->assertRedirect(route('admin.sms.index', ['workspace' => 'history', 'direction' => 'inbound']))
            ->assertSessionHas('sms.history_phone', '+639171234567');
        $this->get(route('admin.sms.index', ['workspace' => 'history']))->assertOk()->assertSee('name="phone"', false)
            ->assertDontSee('phone=%2B639171234567', false);
    }

    public function test_blocked_confirmation_displays_the_validation_reason_after_redirect(): void
    {
        config(['sms.live_enabled' => false]);
        $campaign = $this->campaign();
        $this->recipient($campaign);
        $digest = app(SmsCampaignService::class)->preview($campaign)['digest'];
        $this->followingRedirects()->actingAs($this->admin)->from(route('admin.sms.review', $campaign))
            ->post(route('admin.sms.confirm', $campaign), ['digest' => $digest, 'confirm_audience' => 1])
            ->assertOk()->assertSee('Live SMS is disabled');
    }
}
