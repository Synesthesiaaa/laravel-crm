<?php

namespace Tests\Feature\Admin;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Policies\EmailCampaignPolicy;
use App\Services\Email\CampaignLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailCampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_only_owned_attachment_free_marketing_campaigns(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($owner);
        $policy = new EmailCampaignPolicy;
        $this->assertTrue($policy->view($owner, $campaign));
        $this->assertFalse($policy->view($other, $campaign));
        $campaign->purpose = 'payroll';
        $this->assertFalse($policy->view($owner, $campaign));
    }

    public function test_approved_campaign_schedule_stores_utc_and_no_inline_delivery(): void
    {
        Bus::fake();
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->schedule($actor, $campaign, '2026-11-15T09:00', 'Asia/Manila');
        $campaign->refresh();
        $this->assertSame('scheduled', $campaign->status);
        $this->assertSame('2026-11-15 01:00', $campaign->scheduled_at->utc()->format('Y-m-d H:i'));
        $this->assertSame(3, $campaign->delivery_snapshot_version);
        $this->assertNotEmpty($campaign->recipients()->first()->delivery_snapshot_hash);
        Bus::assertNothingDispatched();
    }

    public function test_dst_ambiguous_local_time_requires_explicit_offset(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        $this->expectException(ValidationException::class);
        app(CampaignLifecycle::class)->schedule($actor, $campaign, '2026-11-01T01:30', 'America/New_York');
    }

    public function test_pause_cancel_and_resume_invalidate_old_dispatch_generation(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $generation = $campaign->fresh()->generation;
        app(CampaignLifecycle::class)->pause($actor, $campaign->fresh());
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertGreaterThan($generation, $campaign->fresh()->generation);
        app(CampaignLifecycle::class)->resume($actor, $campaign->fresh());
        $this->assertSame('queued', $campaign->fresh()->status);
        app(CampaignLifecycle::class)->cancel($actor, $campaign->fresh());
        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertSame('cancelled', $campaign->recipients()->first()->status);
    }

    public function test_unknown_consent_prevents_new_campaign_approval(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        $campaign->recipients()->update(['consent_status' => 'unknown']);
        $this->expectException(ValidationException::class);
        app(CampaignLifecycle::class)->start($actor, $campaign);
    }

    public function test_admin_workspace_scopes_campaigns_and_draft_creation(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $mine = $this->campaign($owner);
        $theirs = $this->campaign($other);
        $theirs->update(['name' => 'Private other campaign']);
        $this->actingAs($owner)->get(route('admin.email-management.index'))->assertOk()->assertSee($mine->name)->assertDontSee('Private other campaign');
        $this->get(route('admin.email-management.index', ['to' => '2030-01-01']))->assertOk()->assertSee($mine->name);
        $this->get(route('admin.email-management.show', $mine))->assertOk()->assertSee('Recipient delivery history');
        $this->get(route('admin.email-management.report', $mine))->assertOk()->assertDownload();
        $this->get(route('admin.email-management.show', $theirs))->assertForbidden();
        $this->post(route('admin.email-management.store'), ['name' => 'New draft', 'email_template_id' => $mine->email_template_id, 'batch_size' => 10, 'delay_seconds' => 5])->assertRedirect();
        $this->assertDatabaseHas('email_campaigns', ['name' => 'New draft', 'management_version' => 3, 'created_by_user_id' => $owner->id]);
        $this->post(route('admin.email-management.store'), ['name' => 'Stolen template', 'email_template_id' => $theirs->email_template_id, 'batch_size' => 10, 'delay_seconds' => 5])->assertSessionHasErrors('email_template_id');
    }

    public function test_real_array_transport_submission_is_accepted_once_and_not_confirmed_delivery(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $campaign->refresh();
        $recipient = $campaign->recipients()->first();
        $messages = 0;
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, function () use (&$messages): void {
            $messages++;
        });
        app(\App\Services\Email\ManagedEmailDelivery::class)->send($campaign->id, $recipient->id, $campaign->generation);
        app(\App\Services\Email\ManagedEmailDelivery::class)->send($campaign->id, $recipient->id, $campaign->generation);
        $this->assertSame(1, $messages);
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertNull($recipient->fresh()->provider_delivered_at);
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertDatabaseCount('email_campaign_attempts', 1);
        $this->assertDatabaseCount('email_campaign_notifications', 1);
    }

    public function test_old_generation_does_not_claim_after_pause_and_resume(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $old = $campaign->fresh()->generation;
        app(CampaignLifecycle::class)->pause($actor, $campaign->fresh());
        app(CampaignLifecycle::class)->resume($actor, $campaign->fresh());
        $this->assertNull(app(\App\Services\EmailCampaignRecipientTransitionService::class)->claim($campaign->id, $campaign->recipients()->first()->id, $old));
        $this->assertDatabaseCount('email_campaign_attempts', 0);
    }

    public function test_provider_rate_reservations_share_a_window_across_campaigns(): void
    {
        $snapshot = ['provider' => 'smtp', 'transport' => ['host' => 'smtp.example.com', 'username' => 'sender'], 'sender' => ['sender@example.com'], 'rate_per_minute' => 2];
        $limiter = app(\App\Services\Email\ProviderRateLimiter::class);
        $this->assertSame(0, $limiter->reserve($snapshot));
        $this->assertSame(0, $limiter->reserve($snapshot));
        $this->assertGreaterThan(0, $limiter->reserve($snapshot));
        $snapshot['sender'] = ['another-sender@example.com'];
        $this->assertGreaterThan(0, $limiter->reserve($snapshot), 'Changing From must not create another account quota.');
        $this->travel(61)->seconds();
        $this->assertSame(0, $limiter->reserve($snapshot));
    }

    public function test_withdrawn_contact_is_skipped_at_submission(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        $contact = \App\Models\EmailContact::forceCreate(['user_id' => $actor->id, 'email' => 'person@example.com', 'consent_status' => 'asserted']);
        $campaign->recipients()->update(['email_contact_id' => $contact->id]);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $contact->update(['consent_status' => 'withdrawn']);
        app(\App\Services\Email\ManagedEmailDelivery::class)->send($campaign->id, $campaign->recipients()->first()->id, $campaign->fresh()->generation);
        $this->assertSame('skipped', $campaign->recipients()->first()->status);
        $this->assertDatabaseHas('email_campaign_attempts', ['status' => 'skipped', 'entered_transport_at' => null]);
    }

    public function test_recovery_does_not_requeue_fresh_delayed_work_every_minute(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        Bus::fake();
        \Illuminate\Support\Facades\DB::table('email_dispatch_intents')->update(['status' => 'completed', 'heartbeat_at' => now()]);
        $this->artisan('email-campaigns:dispatch-due --recover')->assertSuccessful();
        Bus::assertNotDispatched(\App\Jobs\DispatchEmailCampaign::class);
    }

    public function test_revoked_approver_cannot_cause_another_submission(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $actor->update(['role' => User::ROLE_AGENT]);
        app(\App\Services\Email\ManagedEmailDelivery::class)->send($campaign->id, $campaign->recipients()->first()->id, $campaign->fresh()->generation);
        $this->assertNotSame('accepted', $campaign->recipients()->first()->status);
        $this->assertDatabaseMissing('email_campaign_attempts', ['status' => 'accepted']);
    }

    public function test_native_dispatch_registers_bounded_generation_jobs_and_replay_is_safe(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $campaign->refresh();
        Bus::fake();
        (new \App\Jobs\DispatchEmailCampaign($campaign->id, $campaign->generation))->handle();
        Bus::assertBatched(fn (\Illuminate\Bus\PendingBatch $batch) => $batch->jobs->count() === 1 && $batch->jobs->first()->generation === $campaign->generation);
        (new \App\Jobs\DispatchEmailCampaign($campaign->id, $campaign->generation))->handle();
        $this->assertSame('completed', \Illuminate\Support\Facades\DB::table('email_dispatch_intents')->first()->status);
    }

    public function test_consent_withdrawn_before_approval_is_not_frozen(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        $contact = \App\Models\EmailContact::forceCreate(['user_id' => $actor->id, 'email' => 'person@example.com', 'consent_status' => 'withdrawn']);
        $campaign->recipients()->update(['email_contact_id' => $contact->id]);
        $this->expectException(ValidationException::class);
        app(CampaignLifecycle::class)->start($actor, $campaign);
    }

    public function test_operator_notifications_are_durable_and_idempotent(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        app(\App\Services\Email\ManagedEmailDelivery::class)->send($campaign->id, $campaign->recipients()->first()->id, $campaign->fresh()->generation);
        $job = new \App\Jobs\NotifyEmailCampaignOwner($campaign->id, 'terminal:1');
        $job->handle();
        $job->handle();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, app(\App\Services\NotificationService::class)->getDatabaseForUser($actor)->count());
    }

    public function test_http_approval_is_prepared_in_background_without_freezing_the_list_in_request(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        $this->actingAs($actor)->post(route('admin.email-management.action', [$campaign, 'start']), ['confirmed_action' => 1])->assertRedirect();
        $campaign->refresh();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame('queued', $campaign->approval_state);
        $this->assertNull($campaign->delivery_snapshot);
        Bus::assertDispatched(\App\Jobs\ApproveEmailCampaign::class);
        (new \App\Jobs\ApproveEmailCampaign($campaign->id, $campaign->generation))->handle();
        $this->assertSame('queued', $campaign->fresh()->status);
        $this->assertSame('approved', $campaign->fresh()->approval_state);
    }

    public function test_resuming_a_future_scheduled_campaign_does_not_send_early(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->schedule($actor, $campaign, now()->addDay()->format('Y-m-d\TH:i'), 'UTC');
        app(CampaignLifecycle::class)->pause($actor, $campaign->fresh());
        app(CampaignLifecycle::class)->resume($actor, $campaign->fresh());
        $this->assertSame('scheduled', $campaign->fresh()->status);
        Bus::assertNotDispatched(\App\Jobs\DispatchEmailCampaign::class);
    }

    public function test_preparation_readiness_requires_the_matching_worker_heartbeat(): void
    {
        $key = 'email-preparation-worker:email-preparation:email-preparation';
        \Illuminate\Support\Facades\Cache::forget($key);
        $this->assertStringContainsString('no recent heartbeat', app(\App\Services\Email\PreparationQueueStatus::class)->inspect()['problem']);
        \Illuminate\Support\Facades\Cache::put($key, now()->toIso8601String(), 90);
        $this->assertNull(app(\App\Services\Email\PreparationQueueStatus::class)->inspect()['problem']);
        config(['queue.connections.email-preparation.retry_after' => 60]);
        $this->assertStringContainsString('above 300', app(\App\Services\Email\PreparationQueueStatus::class)->inspect()['problem']);
    }

    public function test_resuming_after_last_inflight_recipient_finishes_completes_without_stalling(): void
    {
        Bus::fake();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $campaign = $this->campaign($actor);
        app(CampaignLifecycle::class)->start($actor, $campaign);
        $recipient = $campaign->recipients()->first();
        $transitions = app(\App\Services\EmailCampaignRecipientTransitionService::class);
        $transitions->claim($campaign->id, $recipient->id, $campaign->fresh()->generation);
        app(CampaignLifecycle::class)->pause($actor, $campaign->fresh());
        $transitions->transition($campaign->id, $recipient->id, 'sending', 'accepted', ['sent_at' => now()]);
        app(CampaignLifecycle::class)->resume($actor, $campaign->fresh());
        $this->assertSame('completed', $campaign->fresh()->status);
    }

    private function campaign(User $actor): EmailCampaign
    {
        $template = EmailTemplate::create(['created_by_user_id' => $actor->id, 'name' => 'Update', 'subject' => 'Hello {{name}}', 'html_body' => 'Message for {{email}}']);
        $campaign = EmailCampaign::create(['created_by_user_id' => $actor->id, 'email_template_id' => $template->id, 'name' => 'Marketing update', 'purpose' => 'marketing', 'attachment_mode' => 'none', 'batch_size' => 10, 'delay_seconds' => 1, 'recipient_count' => 1, 'management_version' => 3]);
        EmailCampaignRecipient::forceCreate(['email_campaign_id' => $campaign->id, 'email' => 'person@example.com', 'name' => 'Person', 'status' => 'pending', 'consent_status' => 'asserted', 'consent_source' => 'Documented customer subscription', 'consent_asserted_at' => now()]);

        return $campaign;
    }
}
