<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\TelephonyCallHistory;
use App\Models\User;
use App\Models\VicidialCallHistorySyncState;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CallHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    private VicidialServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vicidial.campaign_scope_cache_seconds' => 0]);
        $this->campaign = Campaign::factory()->create(['code' => 'mbsales']);
        $this->server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $this->campaign->id,
            'vicidial_server_id' => $this->server->id,
            'vicidial_campaign_code' => 'CAMP_A',
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
    }

    public function test_agent_api_returns_only_local_rows_for_the_authenticated_agent(): void
    {
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'vici_user' => 'agent_api',
            'full_name' => 'API Agent',
        ]);
        TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'source_unique_id' => 'api-1',
            'vicidial_user' => 'agent_api',
            'crm_user_id' => $agent->id,
            'call_date' => now()->subMinute(),
        ]);
        TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'source_unique_id' => 'api-2',
            'vicidial_user' => 'other_agent',
            'call_date' => now()->subMinutes(2),
        ]);

        $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history'))
            ->assertOk()
            ->assertJsonPath('data.0.unique_call_id', 'api-1')
            ->assertJsonPath('data.0.agent.name', 'API Agent')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('source_health.source', 'local_database');
    }

    public function test_api_reports_confirmed_empty_separately_from_an_unavailable_scope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        VicidialCallHistorySyncState::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'status' => VicidialCallHistorySyncState::STATUS_HEALTHY,
            'last_successful_sync_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('state', 'confirmed_empty')
            ->assertJsonPath('source_health.status', 'healthy');

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history', ['vicidial_campaign' => 'NOT_MAPPED']))
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('state', 'unavailable')
            ->assertJsonPath('source_health.classification', 'UNAUTHORIZED_SCOPE');
    }
}
