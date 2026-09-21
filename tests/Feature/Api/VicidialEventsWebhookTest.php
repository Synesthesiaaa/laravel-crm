<?php

namespace Tests\Feature\Api;

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

    public function test_webhook_returns_503_when_secret_missing_in_production(): void
    {
        $this->setEnvironment('production');
        config(['vicidial.events_webhook_secret' => '']);

        try {
            $response = $this->postJson(route('api.webhooks.vicidial-events'), [
                'user' => 'testagent',
                'event' => 'logged_in',
                'message' => 'ready',
            ]);

            $response->assertStatus(503);
            $response->assertJsonPath('error', 'Webhook secret is not configured');
        } finally {
            $this->setEnvironment('testing');
            config(['vicidial.events_webhook_secret' => '']);
        }
    }
}
