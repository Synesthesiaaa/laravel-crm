<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ClientErrorTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_error_logs_only_bounded_safe_metadata(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($user): bool {
                return $message === 'Client-side error reported'
                    && $context['route'] === 'api.client-errors'
                    && $context['error_type'] === 'javascript_error'
                    && $context['line'] === 12
                    && $context['column'] === 4
                    && $context['user_id'] === $user->id
                    && ! array_key_exists('message', $context)
                    && ! array_key_exists('source', $context)
                    && ! array_key_exists('url', $context);
            });

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/client-errors', [
                'error_type' => 'javascript_error',
                'line' => 12,
                'column' => 4,
                'message' => 'customer phone 15551234567 synthetic-secret',
                'source' => 'https://crm.example/app.js?token=synthetic-secret',
                'url' => 'https://crm.example/records?customer=15551234567',
                'stack' => 'synthetic sensitive stack',
            ])
            ->assertOk()
            ->assertExactJson(['ok' => true]);
    }

    public function test_client_error_rejects_unknown_categories_and_unbounded_coordinates(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/client-errors', ['error_type' => 'arbitrary', 'line' => 1000001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['error_type', 'line']);
    }

    public function test_rate_limit_exception_is_logged_once_at_the_exception_boundary(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $rateLimitLogger = Mockery::mock();
        $rateLimitLogger->shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'HTTP request throttled'
                    && $context['route'] === 'api.client-errors'
                    && $context['method'] === 'POST'
                    && ! array_key_exists('ip', $context)
                    && ! array_key_exists('user_agent', $context);
            });
        Log::shouldReceive('channel')->once()->with('rate-limit')->andReturn($rateLimitLogger);
        Log::shouldReceive('warning')->times(30)->with('Client-side error reported', Mockery::type('array'));

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales']);

        for ($attempt = 1; $attempt <= 31; $attempt++) {
            $response = $this->postJson('/api/client-errors', ['error_type' => 'javascript_error']);
            $response->assertStatus($attempt <= 30 ? 200 : 429);
        }
    }
}
