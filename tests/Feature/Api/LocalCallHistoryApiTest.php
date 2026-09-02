<?php

namespace Tests\Feature\Api;

use App\Jobs\SyncVicidialCallHistoryJob;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\TelephonyCallHistory;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class LocalCallHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    private VicidialServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vicidial.campaign_scope_cache_seconds' => 0]);
        $this->campaign = Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        $this->server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $this->campaign->id,
            'vicidial_server_id' => $this->server->id,
            'vicidial_campaign_code' => 'CAMP_A',
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
    }

    public function test_agent_api_reads_local_rows_and_keeps_a_personal_scope(): void
    {
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'vici_user' => 'agent_api',
            'full_name' => 'API Agent',
        ]);
        TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'source_unique_id' => 'local-agent-call',
            'vicidial_user' => 'agent_api',
            'crm_user_id' => $agent->id,
            'call_date' => now()->subMinutes(2),
            'call_started_at' => now()->subMinutes(4),
            'call_ended_at' => now()->subMinutes(2),
        ]);
        TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'source_unique_id' => 'local-other-call',
            'vicidial_user' => 'other_agent',
            'call_date' => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history', ['per_page' => 15]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('state', 'data')
            ->assertJsonPath('data.0.unique_call_id', 'local-agent-call')
            ->assertJsonPath('data.0.agent.name', 'API Agent')
            ->assertJsonPath('source_health.source', 'local_database')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_local_api_applies_lead_phone_and_mapped_campaign_filters(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'source_unique_id' => 'matching-call',
            'lead_id' => 7001,
            'phone_number' => '639121234567',
            'vicidial_campaign_id' => 'CAMP_A',
            'call_date' => now()->subMinutes(2),
        ]);
        TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
            'source_unique_id' => 'other-campaign-call',
            'lead_id' => 7001,
            'phone_number' => '639121234567',
            'vicidial_campaign_id' => 'CAMP_OTHER',
            'call_date' => now()->subMinutes(1),
        ]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history', [
                'lead_id' => 7001,
                'phone' => '09121234567',
                'vicidial_campaign' => 'CAMP_A',
            ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.unique_call_id', 'matching-call');
    }

    public function test_refresh_is_authenticated_and_duplicate_requests_are_suppressed(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.call-history.refresh'))
            ->assertStatus(202)
            ->assertJsonPath('state', 'queued')
            ->assertJsonPath('duplicate_suppressed', false);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.call-history.refresh'))
            ->assertStatus(202)
            ->assertJsonPath('duplicate_suppressed', true);

        $this->assertDatabaseHas('vicidial_call_history_sync_states', [
            'vicidial_server_id' => $this->server->id,
            'crm_campaign_id' => $this->campaign->id,
        ]);
        Queue::assertPushed(SyncVicidialCallHistoryJob::class, 1);
    }
}
