<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmiWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_SECRET = 'synthetic-ami-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['asterisk.webhook_secret' => self::TEST_SECRET]);
    }

    private function setEnvironment(string $environment): void
    {
        app()->detectEnvironment(fn () => $environment);
        config(['app.env' => $environment]);
    }

    public function test_webhook_returns_received_when_event_missing(): void
    {
        $response = $this->postJson(route('api.webhooks.ami'), [], $this->validSecretHeader());
        $response->assertOk();
        $response->assertJson(['received' => true, 'processed' => false]);
    }

    public function test_webhook_unprocessed_when_event_not_handled(): void
    {
        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'VarSet',
            'linkedid' => 'xyz',
        ], $this->validSecretHeader());
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
        ], $this->validSecretHeader());

        $response->assertOk();
        $response->assertJson(['received' => true, 'processed' => true]);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_bridge_event_connects_a_ringing_session(): void
    {
        $user = User::factory()->create(['extension' => '1003']);
        $session = CallSession::factory()
            ->for($user)
            ->ringing()
            ->withLinkedId('1699123456.321')
            ->create();

        $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Bridge',
            'linkedid' => '1699123456.321',
            'channel' => 'PJSIP/1003-00000003',
        ], $this->validSecretHeader())
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertSame(CallSession::STATUS_IN_CALL, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->answered_at);
    }

    public function test_answer_dial_status_connects_a_dialing_session(): void
    {
        $user = User::factory()->create(['extension' => '1004']);
        $session = CallSession::factory()
            ->for($user)
            ->dialing()
            ->withLinkedId('1699123456.654')
            ->create();

        $this->postJson(route('api.webhooks.ami'), [
            'event' => 'DialEnd',
            'dialstatus' => 'ANSWER',
            'linkedid' => '1699123456.654',
            'channel' => 'PJSIP/1004-00000004',
        ], $this->validSecretHeader())
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertSame(CallSession::STATUS_IN_CALL, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->answered_at);
    }

    public function test_hangup_not_processed_when_no_matching_session(): void
    {
        $response = $this->postJson(route('api.webhooks.ami'), [
            'event' => 'Hangup',
            'linkedid' => 'nonexistent-linkedid',
            'channel' => 'PJSIP/9999-00000001',
        ], $this->validSecretHeader());

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
        ], $this->validSecretHeader());

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

    public function test_webhook_returns_503_when_secret_is_missing_in_any_environment(): void
    {
        foreach (['testing', 'staging', 'production'] as $environment) {
            $this->setEnvironment($environment);
            config(['asterisk.webhook_secret' => '']);
            $response = $this->postJson(route('api.webhooks.ami'), [
                'event' => 'Hangup',
                'linkedid' => 'xyz',
            ]);

            $response->assertStatus(503)
                ->assertJsonPath('error', 'Webhook secret is not configured');
        }

        $this->setEnvironment('testing');
    }

    /** @return array<string, string> */
    private function validSecretHeader(): array
    {
        return ['X-Webhook-Secret' => self::TEST_SECRET];
    }
}
