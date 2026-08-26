<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CampaignDispositionRecord;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Models\VicidialServer;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupervisorAgentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_data_is_scoped_to_the_active_campaign_and_includes_routing_context(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        Campaign::factory()->create(['code' => 'campaign-b', 'name' => 'Campaign B']);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A VICIdial',
            'is_default' => true,
        ]);

        $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'full_name' => 'Campaign A Agent',
            'default_campaign' => 'campaign-a',
        ]);
        AttendanceLog::create([
            'user_id' => $agent->id,
            'event_type' => 'login',
            'event_time' => now(),
        ]);
        VicidialAgentSession::factory()->create([
            'user_id' => $agent->id,
            'campaign_code' => 'campaign-a',
            'session_status' => 'ready',
            'last_status_payload' => ['queue_count' => 4],
        ]);
        VicidialAgentSession::factory()->create([
            'user_id' => $agent->id,
            'campaign_code' => 'campaign-b',
            'session_status' => 'paused',
            'last_status_payload' => ['queue_count' => 99],
        ]);
        CallSession::factory()->create([
            'user_id' => $agent->id,
            'campaign_code' => 'campaign-a',
            'status' => CallSession::STATUS_COMPLETED,
            'dialed_at' => now(),
        ]);
        CallSession::factory()->create([
            'user_id' => $agent->id,
            'campaign_code' => 'campaign-b',
            'status' => CallSession::STATUS_COMPLETED,
            'dialed_at' => now(),
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'campaign-a',
            'agent' => $agent->full_name,
            'disposition_code' => 'SALE',
            'called_at' => now(),
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'campaign-b',
            'agent' => $agent->full_name,
            'disposition_code' => 'SALE',
            'called_at' => now(),
        ]);

        $response = $this->actingAs($supervisor)
            ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
            ->getJson(route('api.supervisor.agents'));

        $response->assertOk()
            ->assertJsonPath('routing.campaign_code', 'campaign-a')
            ->assertJsonPath('routing.campaign_name', 'Campaign A')
            ->assertJsonPath('routing.server_name', 'Campaign A VICIdial')
            ->assertJsonPath('stats.todayTotal', 1)
            ->assertJsonPath('agents.0.campaign_code', 'campaign-a')
            ->assertJsonPath('agents.0.queue_count', 4)
            ->assertJsonPath('agents.0.dispositions', 1);
    }

    public function test_unmapped_campaign_returns_an_actionable_routing_state_without_connection_details(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($supervisor)
            ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
            ->getJson(route('api.supervisor.agents'));

        $response->assertOk()
            ->assertJsonPath('routing.campaign_code', 'campaign-a')
            ->assertJsonPath('routing.configured', false)
            ->assertJsonPath('routing.message', "No VICIdial server is configured for campaign 'campaign-a'.")
            ->assertJsonMissingPath('routing.api_url')
            ->assertJsonMissingPath('routing.api_pass');
    }

    public function test_query_campaign_selects_its_server_even_when_vicidial_campaign_differs(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        Campaign::factory()->create(['code' => 'campaign-b', 'name' => 'Campaign B']);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A VICIdial',
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-b',
            'server_name' => 'Campaign B VICIdial',
        ]);
        $this->app->make(CampaignService::class)->clearCampaignsCache();

        $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($supervisor)
            ->withSession([
                'campaign' => 'campaign-a',
                'campaign_name' => 'Campaign A',
                'vicidial_campaign' => 'softcamp',
            ])
            ->getJson(route('api.supervisor.agents', ['campaign' => 'campaign-b']));

        $response->assertOk()
            ->assertJsonPath('routing.campaign_code', 'campaign-b')
            ->assertJsonPath('routing.campaign_name', 'Campaign B')
            ->assertJsonPath('routing.server_name', 'Campaign B VICIdial');
    }

    public function test_supervisor_uses_the_mapped_server_agent_list_without_filtering_on_vicidial_campaign(): void
    {
        Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A VICIdial',
            'api_url' => 'https://campaign-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);
        $this->app->make(CampaignService::class)->clearCampaignsCache();

        $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $remoteAgent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'full_name' => 'Remote Agent',
            'vici_user' => 'remote-agent',
            'default_campaign' => 'other-vicidial-campaign',
        ]);
        Http::fake([
            'https://campaign-a.example/non_agent_api.php*' => Http::response(
                "user|status|queue_count\nremote-agent|INCALL|3",
                200,
            ),
        ]);

        $response = $this->actingAs($supervisor)
            ->withSession([
                'campaign' => 'campaign-a',
                'campaign_name' => 'Campaign A',
                'vicidial_campaign' => 'other-vicidial-campaign',
            ])
            ->getJson(route('api.supervisor.agents'));

        $response->assertOk()
            ->assertJsonPath('agents.0.id', $remoteAgent->id)
            ->assertJsonPath('agents.0.campaign_code', 'campaign-a')
            ->assertJsonPath('agents.0.status', 'oncall')
            ->assertJsonPath('agents.0.vici_status', 'INCALL')
            ->assertJsonPath('agents.0.queue_count', 3);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php')
                && ($request->data()['campaigns'] ?? null) === '---ALL---';
        });
    }
}
