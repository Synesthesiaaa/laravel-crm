<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallDialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dial_requires_vicidial_agent_session_when_enabled(): void
    {
        config(['vicidial.require_vicidial_agent_session_before_dial' => true]);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'secret',
            'extension' => '6001',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB'])
            ->postJson('/api/call/dial?campaign=mbsales', ['phone_number' => '15551234567'])
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'VICIDIAL_AGENT_NOT_LOGGED_IN');
    }
}
