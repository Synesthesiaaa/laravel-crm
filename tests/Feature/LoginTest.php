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
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
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
        Http::assertNothingSent();
    }
}
