<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
