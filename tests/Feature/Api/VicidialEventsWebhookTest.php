<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VicidialEventsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function setEnvironment(string $environment): void
    {
        app()->detectEnvironment(fn () => $environment);
        config(['app.env' => $environment]);
    }

    public function test_webhook_processes_events_when_secret_matches(): void
    {
        config(['vicidial.events_webhook_secret' => 'correct-secret']);

        $response = $this->postJson(route('api.webhooks.vicidial-events'), [
            'user' => 'testagent',
            'event' => 'logged_in',
            'message' => 'ready',
        ], [
            'X-Webhook-Secret' => 'correct-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('processed', true)
            ->assertJsonPath('event', 'logged_in');
    }

    public function test_webhook_rejects_invalid_secret_when_configured(): void
    {
        config(['vicidial.events_webhook_secret' => 'correct-secret']);

        $response = $this->postJson(route('api.webhooks.vicidial-events'), [
            'user' => 'testagent',
            'event' => 'logged_in',
            'message' => 'ready',
        ], [
            'X-Webhook-Secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Unauthorized');
    }

    public function test_webhook_returns_503_when_secret_is_missing_in_any_environment(): void
    {
        foreach (['testing', 'staging', 'production'] as $environment) {
            $this->setEnvironment($environment);
            config(['vicidial.events_webhook_secret' => '']);
            $response = $this->postJson(route('api.webhooks.vicidial-events'), [
                'user' => 'testagent',
                'event' => 'logged_in',
                'message' => 'ready',
            ]);

            $response->assertStatus(503)
                ->assertJsonPath('error', 'Webhook secret is not configured');
        }

        $this->setEnvironment('testing');
    }

    public function test_answer_event_with_unknown_call_id_does_not_connect_an_active_session(): void
    {
        config(['vicidial.events_webhook_secret' => 'correct-secret']);
        $user = User::factory()->create([
            'username' => 'eventagent',
            'vici_user' => 'eventagent',
        ]);
        $session = CallSession::factory()->for($user)->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 101,
            'vicidial_lead_id' => '101',
            'phone_number' => '15550000101',
            'vicidial_call_id' => 'VICI-EVENT-KNOWN',
            'status' => CallSession::STATUS_RINGING,
        ]);

        $this->postJson(route('api.webhooks.vicidial-events'), [
            'user' => 'eventagent',
            'event' => 'call_answered',
            'call_id' => 'VICI-EVENT-UNKNOWN',
            'campaign' => 'mbsales',
            'lead_id' => 101,
            'counter' => 999,
        ], [
            'X-Webhook-Secret' => 'correct-secret',
        ])
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertSame(CallSession::STATUS_RINGING, $session->fresh()->status);
    }

    public function test_end_event_correlates_to_its_call_id_and_leaves_newer_session_active(): void
    {
        config(['vicidial.events_webhook_secret' => 'correct-secret']);
        $user = User::factory()->create([
            'username' => 'eventagent2',
            'vici_user' => 'eventagent2',
        ]);
        $olderSession = CallSession::factory()->for($user)->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 201,
            'vicidial_lead_id' => '201',
            'phone_number' => '15550000201',
            'vicidial_call_id' => 'VICI-EVENT-OLDER',
            'status' => CallSession::STATUS_IN_CALL,
        ]);
        $newerSession = CallSession::factory()->for($user)->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 202,
            'vicidial_lead_id' => '202',
            'phone_number' => '15550000202',
            'vicidial_call_id' => 'VICI-EVENT-NEWER',
            'status' => CallSession::STATUS_DIALING,
        ]);

        $this->postJson(route('api.webhooks.vicidial-events'), [
            'user' => 'eventagent2',
            'event' => 'agent_hangup',
            'call_id' => 'VICI-EVENT-OLDER',
            'campaign' => 'mbsales',
            'lead_id' => 201,
            'counter' => 1,
        ], [
            'X-Webhook-Secret' => 'correct-secret',
        ])
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertSame(CallSession::STATUS_COMPLETED, $olderSession->fresh()->status);
        $this->assertSame(CallSession::STATUS_DIALING, $newerSession->fresh()->status);
    }
}
