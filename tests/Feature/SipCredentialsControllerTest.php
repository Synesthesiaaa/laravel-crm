<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SipCredentialsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_sip_credentials_are_returned_as_an_available_capability_state(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('api.sip.credentials'));

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No SIP extension assigned to this account. Contact your administrator.');
    }

    public function test_configured_sip_credentials_are_returned_for_registration(): void
    {
        $user = User::factory()->create([
            'extension' => '6001',
            'sip_password' => 'secret',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.sip.credentials'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('extension', '6001')
            ->assertJsonPath('password', 'secret');
    }
}
