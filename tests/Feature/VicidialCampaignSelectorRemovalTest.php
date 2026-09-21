<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VicidialCampaignSelectorRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_campaigns_route_is_removed(): void
    {
        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
        ]);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'testcamp',
                'campaign_name' => 'Test Camp',
            ])
            ->getJson('/api/vicidial/session/agent-campaigns')
            ->assertNotFound();
    }

    public function test_select_campaign_route_is_removed(): void
    {
        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
        ]);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'testcamp',
                'campaign_name' => 'Test Camp',
            ])
            ->postJson('/api/vicidial/session/select-campaign', [
                'campaign' => 'newcamp',
                'campaign_name' => 'New Camp',
            ])
            ->assertNotFound();
    }
}
