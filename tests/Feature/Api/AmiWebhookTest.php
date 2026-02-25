<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\User;
use App\Models\UnmatchedAmiEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmiWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_returns_received_when_event_missing(): void
    {
        $response = $this->postJson(route('api.webhooks.ami'), []);
        $response->assertOk();
        $response->assertJson(['received' => true, 'processed' => false]);
    }

    public function test_webhook_unprocessed_when_event_not_handled(): void
    {
        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'VarSet',
            'linkedid' => 'xyz',
        ]);
        $response->assertOk();
        $response->assertJson(['received' => true, 'processed' => false]);
    }

    public function test_hangup_processed_when_session_matches_by_linkedid(): void
    {
        $user = User::factory()->create(['extension' => '1001']);
        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->withLinkedId('1699123456.123')
            ->create();

        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Hangup',
            'linkedid' => '1699123456.123',
            'channel' => 'PJSIP/1001-00000001',
        ]);

        $response->assertOk();
        $response->assertJson(['received' => true, 'processed' => true]);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_hangup_not_processed_when_no_matching_session(): void
    {
        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Hangup',
            'linkedid' => 'nonexistent-linkedid',
            'channel' => 'PJSIP/9999-00000001',
        ]);

        $response->assertOk();
        $response->assertJson(['received' => true, 'processed' => false, 'reason' => 'no matching session']);
        $this->assertDatabaseCount('unmatched_ami_events', 1);
    }

    public function test_hangup_processed_by_extension_fallback_when_no_linkedid_match(): void
    {
        $user = User::factory()->create(['extension' => '1002']);
        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create(['linkedid' => null, 'channel' => null]);

        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Hangup',
            'channel' => 'PJSIP/1002-00000002',
        ]);

        $response->assertOk();
        $response->assertJson(['processed' => true]);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
    }

    public function test_webhook_rejects_invalid_secret_when_configured(): void
    {
        config(['asterisk.webhook_secret' => 'my-secret']);
        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Hangup',
            'linkedid' => 'xyz',
        ], ['X-Webhook-Secret' => 'wrong-secret']);
        $response->assertStatus(401);
        config(['asterisk.webhook_secret' => '']);
    }

    public function test_webhook_accepts_correct_secret(): void
    {
        config(['asterisk.webhook_secret' => 'correct-secret']);
        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Hangup',
            'linkedid' => 'nonexistent',
        ], ['X-Webhook-Secret' => 'correct-secret']);
        $response->assertOk();
        config(['asterisk.webhook_secret' => '']);
    }
}
