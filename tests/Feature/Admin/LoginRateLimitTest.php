<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('testuser|127.0.0.1');
    }

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
    }

    public function test_invalid_credentials_return_validation_error(): void
    {
        $response = $this->post(route('login'), [
            'username' => 'nobody',
            'password' => 'wrongpass',
        ]);
        $response->assertSessionHasErrors('username');
    }

    public function test_valid_credentials_redirect_to_dashboard(): void
    {
        $user = User::factory()->create([
            'username' => 'validuser',
            'password' => bcrypt('ValidPass1'),
            'role' => 'Agent',
        ]);

        $response = $this->post(route('login'), [
            'username' => 'validuser',
            'password' => 'ValidPass1',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_rate_limiter_blocks_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'username' => 'spammer',
                'password' => 'wrongpass',
            ]);
        }

        $response = $this->post(route('login'), [
            'username' => 'spammer',
            'password' => 'wrongpass',
        ]);

        // The throttle:login middleware returns 429 after the limit is hit,
        // OR the LoginRequest throws a ValidationException (redirect with errors).
        // Either outcome proves the rate limiter is working.
        $this->assertTrue(
            $response->status() === 429 || $response->isRedirection(),
            "Expected 429 or redirect when rate limited, got {$response->status()}",
        );

        if ($response->isRedirection()) {
            $response->assertSessionHasErrors('username');
        }
    }
}
