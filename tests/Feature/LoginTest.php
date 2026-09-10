<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VicidialAgentSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_guest_to_login_page(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));
    }

    public function test_guest_login_page_renders_the_accessible_minimal_form_structure(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('<main class="login-content" aria-labelledby="login-title">', false)
            ->assertSee('<section class="login-glass-card" aria-labelledby="login-title">', false)
            ->assertSee('id="login-title"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="campaign"', false)
            ->assertSee('id="theme-toggle"', false);
    }

    public function test_login_page_marks_the_field_with_login_validation_feedback(): void
    {
        User::factory()->create(['username' => 'validation-agent']);

        $this->from(route('login'))->post(route('login'), [
            'username' => 'validation-agent',
            'password' => 'wrong-password',
        ]);

        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('id="login-error"', false)
            ->assertSee('id="username"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="login-error"', false);
    }

    public function test_login_success_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['username' => 'testagent']);
        $response = $this->post(route('login'), [
            'username' => 'testagent',
            'password' => 'password',
            'campaign' => 'mbsales',
        ]);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_succeeds_when_the_activity_broadcaster_is_unavailable(): void
    {
        config(['broadcasting.default' => 'unavailable-broadcast-connection']);

        $user = User::factory()->create(['username' => 'broadcast-outage-login']);

        $response = $this->post(route('login'), [
            'username' => 'broadcast-outage-login',
            'password' => 'password',
            'campaign' => 'mbsales',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id,
            'event_type' => 'login',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $user->id,
            'event' => 'login',
            'description' => 'User logged in',
        ]);
    }

    public function test_login_continues_when_vicidial_auto_bootstrap_is_staged(): void
    {
        config(['vicidial.auto_bootstrap_on_crm_login' => true]);

        $user = User::factory()->create([
            'username' => 'autoviciagent',
            'vici_user' => 'agent1',
            'vici_pass' => 'secret',
            'extension' => '6001',
            'auto_vici_login' => true,
        ]);

        $response = $this->post(route('login'), [
            'username' => 'autoviciagent',
            'password' => 'password',
            'campaign' => 'mbsales',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('telephony_bootstrap');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['username' => 'testagent']);
        $response = $this->post(route('login'), [
            'username' => 'testagent',
            'password' => 'wrongpassword',
        ]);
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_logout_redirects_to_login_and_marks_vicidial_session_logged_out_locally(): void
    {
        config(['broadcasting.default' => 'unavailable-broadcast-connection']);
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_AGENT]);
        VicidialAgentSession::factory()->create([
            'user_id' => $user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'ready',
            'pause_code' => 'BREAK',
            'last_iframe_url' => 'https://vici.example.com/agc/vicidial.php?x=1',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'campaign' => 'testcamp',
                'campaign_name' => 'Test Campaign',
                'vicidial_campaign' => 'testcamp',
                'vicidial_campaign_name' => 'Test Campaign',
            ])
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'logged_out',
            'pause_code' => null,
            'last_iframe_url' => null,
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id,
            'event_type' => 'logout',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $user->id,
            'event' => 'logout',
            'description' => 'User logged out',
        ]);
        Http::assertNothingSent();
    }
}
