<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_screen_includes_single_pane_workflow_help(): void
    {
        $agent = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
        ]);

        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test'])
            ->get('/agent');

        $response->assertOk();
        $response->assertSee('Outbound workflow (single CRM tab)', false);
        $response->assertSee('this CRM tab only', false);
        $response->assertSee('Do not open VICIdial in a second browser tab', false);
        $response->assertSee('Telephony and call controls', false);
        $response->assertSee('Keyboard', false);
        $response->assertSee('Esc', false);

        $response->assertSee('id="vici-session-frame"', false);
        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('title="VICIdial session binding (hidden, not for manual use)"', false);

        $response->assertSee('id="agent-disposition-panel"', false);
        $response->assertSee('id="agent-go-to-disposition-link"', false);
        $response->assertSee('Go to disposition', false);
        $response->assertSee('aria-labelledby="agent-disposition-heading"', false);
    }
}
