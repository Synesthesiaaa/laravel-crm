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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
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

    public function test_supervisor_prefers_mapped_vicidial_call_totals_when_crm_has_no_call_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
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
            Http::fake(function ($request) {
                return match ($request->data()['function'] ?? null) {
                    'logged_in_agents' => Http::response(
                        "user|status|calls_today\nremote-agent|READY|7",
                        200,
                    ),
                    'call_status_stats' => Http::response(
                        'campaign/ingroup|7|5|10-2,11-5|SALE-7',
                        200,
                    ),
                    default => Http::response('', 200),
                };
            });

            $response = $this->actingAs($supervisor)
                ->withSession([
                    'campaign' => 'campaign-a',
                    'campaign_name' => 'Campaign A',
                ])
                ->getJson(route('api.supervisor.agents'));

            $response->assertOk()
                ->assertJsonPath('stats.todayTotal', 7)
                ->assertJsonPath('stats.callsAnswered', 5)
                ->assertJsonPath('stats.answerRate', 71.4)
                ->assertJsonPath('stats.callsByHour.10', 2)
                ->assertJsonPath('stats.callsByHour.11', 5)
                ->assertJsonPath('stats.callSource', 'vicidial')
                ->assertJsonPath('agents.0.id', $remoteAgent->id)
                ->assertJsonPath('agents.0.calls_today', 7);

            Http::assertSent(function ($request): bool {
                return str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php')
                    && ($request->data()['function'] ?? null) === 'call_status_stats'
                    && ($request->data()['campaigns'] ?? null) === '---ALL---'
                    && ($request->data()['query_date'] ?? null) === '2026-08-26';
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supervisor_uses_realtime_and_agent_stats_from_only_the_crm_campaign_server(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
            Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
            Campaign::factory()->create(['code' => 'campaign-b', 'name' => 'Campaign B']);
            VicidialServer::factory()->create([
                'campaign_code' => 'campaign-a',
                'server_name' => 'Campaign A VICIdial',
                'api_url' => 'https://campaign-a.example/agc/api.php',
                'api_user' => 'campaign-a-user',
                'api_pass' => 'campaign-a-pass',
            ]);
            VicidialServer::factory()->create([
                'campaign_code' => 'campaign-b',
                'server_name' => 'Campaign B VICIdial',
                'api_url' => 'https://campaign-b.example/agc/api.php',
                'api_user' => 'campaign-b-user',
                'api_pass' => 'campaign-b-pass',
            ]);
            $this->app->make(CampaignService::class)->clearCampaignsCache();

            $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $remoteAgent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'full_name' => 'Remote Agent',
                'vici_user' => 'agent-a',
                'default_campaign' => 'another-vicidial-campaign',
            ]);
            Http::fake(function ($request) {
                if (! str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php')) {
                    return Http::response('ERROR: wrong server', 500);
                }

                return match ($request->data()['function'] ?? null) {
                    'logged_in_agents' => Http::response(
                        "user|status|calls_today|user_group|sub_status\nagent-a|INCALL|4|SALES|RING\nunknown-agent|READY|2|SALES|",
                        200,
                    ),
                    'agent_stats_export' => Http::response(
                        "user|user_group|calls|total_talk_time|avg_talk_time|avg_wait_time|total_wait_time\nagent-a|SALES|4|480|120|30|120",
                        200,
                    ),
                    'call_status_stats' => Http::response(
                        'remote-campaign|9|6|10-4,11-5|SALE-9',
                        200,
                    ),
                    'user_group_status' => Http::response(
                        "usergroups|calls_waiting|agents_logged_in|agents_in_calls|agents_waiting|agents_paused|agents_in_dead_calls|agents_in_dispo|agents_in_dial\nSALES|3|2|1|1|0|0|0|0",
                        200,
                    ),
                    default => Http::response('ERROR: unsupported test function', 400),
                };
            });

            $response = $this->actingAs($supervisor)
                ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
                ->getJson(route('api.supervisor.agents'));

            $response->assertOk()
                ->assertJsonPath('routing.server_name', 'Campaign A VICIdial')
                ->assertJsonPath('stats.agentsOnline', 2)
                ->assertJsonPath('stats.agentsAvailable', 1)
                ->assertJsonPath('stats.agentsOnCall', 1)
                ->assertJsonPath('stats.agentsPaused', 0)
                ->assertJsonPath('stats.callsWaiting', 3)
                ->assertJsonPath('stats.callsActive', 1)
                ->assertJsonPath('stats.avgWaitTime', 30)
                ->assertJsonPath('stats.avgHandleTime', 120)
                ->assertJsonPath('stats.todayTotal', 9)
                ->assertJsonPath('stats.callsAnswered', 6)
                ->assertJsonPath('stats.realtimeSource', 'vicidial')
                ->assertJsonPath('stats.performanceSource', 'vicidial')
                ->assertJsonPath('stats.callSource', 'vicidial')
                ->assertJsonPath('routing.reporting_status', 'live')
                ->assertJsonPath('routing.message', null)
                ->assertJsonPath('agents.0.id', $remoteAgent->id)
                ->assertJsonPath('agents.0.status', 'oncall')
                ->assertJsonPath('agents.0.calls_today', 4)
                ->assertJsonPath('agents.0.avg_handle', 120)
                ->assertJsonPath('agents.0.avg_wait', 30)
                ->assertJsonMissingPath('routing.api_url')
                ->assertJsonMissingPath('routing.api_pass');

            Http::assertSentCount(4);
            Http::assertSent(function ($request): bool {
                return str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php')
                    && ($request->data()['function'] ?? null) === 'agent_stats_export'
                    && ($request->data()['group_by_campaign'] ?? null) === 'NO'
                    && ($request->data()['time_format'] ?? null) === 'S'
                    && ! isset($request->data()['campaign_id']);
            });
            Http::assertSent(function ($request): bool {
                return ($request->data()['function'] ?? null) === 'user_group_status'
                    && ($request->data()['user_groups'] ?? null) === 'SALES';
            });
            Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'https://campaign-b.example/'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supervisor_ignores_malformed_agent_stats_and_keeps_crm_timing_fallbacks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
            Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
            VicidialServer::factory()->create([
                'campaign_code' => 'campaign-a',
                'api_url' => 'https://campaign-a.example/agc/api.php',
                'api_user' => 'report-user',
                'api_pass' => 'report-pass',
            ]);
            $this->app->make(CampaignService::class)->clearCampaignsCache();

            $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'vici_user' => 'agent-a',
                'default_campaign' => 'campaign-a',
            ]);
            CallSession::factory()->for($agent)->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_COMPLETED,
                'dialed_at' => now()->subMinutes(5),
                'answered_at' => now()->subMinutes(5)->addSeconds(20),
                'ended_at' => now()->subMinutes(5)->addSeconds(110),
                'call_duration_seconds' => 90,
            ]);
            Http::fake(function ($request) {
                return match ($request->data()['function'] ?? null) {
                    'logged_in_agents' => Http::response(
                        "user|status|calls_today|user_group\nagent-a|READY|invalid|SALES",
                        200,
                    ),
                    'agent_stats_export' => Http::response(
                        "user|user_group|calls|avg_talk_time|avg_wait_time\nagent-a|SALES|not-a-number|broken|broken",
                        200,
                    ),
                    'call_status_stats' => Http::response('remote-campaign|1|1|11-1|SALE-1', 200),
                    'user_group_status' => Http::response(
                        "usergroups|calls_waiting|agents_logged_in|agents_in_calls|agents_waiting|agents_paused\nSALES|0|1|0|1|0",
                        200,
                    ),
                    default => Http::response('', 200),
                };
            });

            $response = $this->actingAs($supervisor)
                ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
                ->getJson(route('api.supervisor.agents'));

            $response->assertOk()
                ->assertJsonPath('stats.avgWaitTime', 20)
                ->assertJsonPath('stats.avgHandleTime', 90)
                ->assertJsonPath('stats.performanceSource', 'crm')
                ->assertJsonPath('stats.realtimeSource', 'vicidial')
                ->assertJsonPath('stats.callSource', 'vicidial')
                ->assertJsonPath('routing.reporting_status', 'live')
                ->assertJsonPath('agents.0.calls_today', 1)
                ->assertJsonPath('agents.0.avg_handle', 90)
                ->assertJsonPath('agents.0.avg_wait', 20);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supervisor_falls_back_to_crm_totals_when_vicidial_call_report_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
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
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'default_campaign' => 'campaign-a',
            ]);
            CallSession::factory()->for($agent)->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_COMPLETED,
                'dialed_at' => now()->subMinutes(10),
            ]);
            Http::fake(function ($request) {
                return ($request->data()['function'] ?? null) === 'call_status_stats'
                    ? Http::response('ERROR: report access denied', 200)
                    : Http::response("user|status|calls_today\n", 200);
            });

            $response = $this->actingAs($supervisor)
                ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
                ->getJson(route('api.supervisor.agents'));

            $response->assertOk()
                ->assertJsonPath('stats.todayTotal', 1)
                ->assertJsonPath('stats.callsAnswered', 0)
                ->assertJsonPath('stats.callSource', 'crm')
                ->assertJsonPath('routing.reporting_status', 'degraded')
                ->assertJsonPath('routing.message', 'Some VICIdial reports are unavailable, so CRM fallback metrics may be incomplete. Verify this CRM campaign server\'s API URL and network access, then confirm its API user has View Reports permission (levels 7/8).');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supervisor_returns_safe_unavailable_reporting_diagnostics_when_all_vicidial_reports_fail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
            Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
            VicidialServer::factory()->create([
                'campaign_code' => 'campaign-a',
                'server_name' => 'Campaign A VICIdial',
                'api_url' => 'https://campaign-a.example/agc/api.php',
                'api_user' => 'private-report-user',
                'api_pass' => 'private-report-password',
            ]);
            $this->app->make(CampaignService::class)->clearCampaignsCache();

            $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'default_campaign' => 'campaign-a',
            ]);
            CallSession::factory()->for($agent)->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_COMPLETED,
                'dialed_at' => now()->subMinutes(10),
            ]);
            Http::fake(fn () => throw new ConnectionException(
                'cURL error 7 for https://campaign-a.example/non_agent_api.php?user=private-report-user&pass=private-report-password',
            ));

            $response = $this->actingAs($supervisor)
                ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
                ->getJson(route('api.supervisor.agents'));

            $response->assertOk()
                ->assertJsonPath('stats.todayTotal', 1)
                ->assertJsonPath('stats.callSource', 'crm')
                ->assertJsonPath('routing.reporting_status', 'unavailable')
                ->assertJsonPath('routing.message', 'VICIdial reports are unavailable. Verify this CRM campaign server\'s API URL and network access, then confirm its API user has View Reports permission (levels 7/8).')
                ->assertJsonMissingPath('routing.api_url')
                ->assertJsonMissingPath('routing.api_user')
                ->assertJsonMissingPath('routing.api_pass');
            $this->assertStringNotContainsString('private-report-user', $response->getContent());
            $this->assertStringNotContainsString('private-report-password', $response->getContent());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supervisor_metrics_are_derived_from_the_current_campaign_call_lifecycle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00'));

        try {
            Campaign::factory()->create(['code' => 'campaign-a', 'name' => 'Campaign A']);
            VicidialServer::factory()->create([
                'campaign_code' => 'campaign-a',
                'server_name' => 'Campaign A VICIdial',
            ]);
            $this->app->make(CampaignService::class)->clearCampaignsCache();

            $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'full_name' => 'Metrics Agent',
                'default_campaign' => 'campaign-a',
            ]);
            AttendanceLog::create([
                'user_id' => $agent->id,
                'event_type' => 'login',
                'event_time' => now()->subHours(3),
            ]);

            CallSession::factory()->for($agent)->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_COMPLETED,
                'dialed_at' => now()->subHours(2),
                'answered_at' => now()->subHours(2)->addSeconds(10),
                'ended_at' => now()->subHours(2)->addSeconds(130),
                'call_duration_seconds' => 120,
            ]);
            CallSession::factory()->for($agent)->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_COMPLETED,
                'dialed_at' => now()->subHour(),
                'answered_at' => now()->subHour()->addSeconds(30),
                'ended_at' => now()->subHour()->addSeconds(210),
                'call_duration_seconds' => null,
            ]);
            CallSession::factory()->for($agent)->create([
                'campaign_code' => 'campaign-a',
                'status' => CallSession::STATUS_FAILED,
                'dialed_at' => now()->subMinutes(30),
                'ended_at' => now()->subMinutes(29),
            ]);
            CallSession::factory()->for($agent)->inCall()->create([
                'campaign_code' => 'campaign-a',
                'dialed_at' => now()->subMinutes(10),
            ]);

            $response = $this->actingAs($supervisor)
                ->withSession(['campaign' => 'campaign-a', 'campaign_name' => 'Campaign A'])
                ->getJson(route('api.supervisor.agents'));

            $response->assertOk()
                ->assertJsonPath('stats.agentsOnline', 1)
                ->assertJsonPath('stats.callsActive', 1)
                ->assertJsonPath('stats.todayTotal', 3)
                ->assertJsonPath('stats.callsAnswered', 2)
                ->assertJsonPath('stats.avgWaitTime', 20)
                ->assertJsonPath('stats.avgHandleTime', 150)
                ->assertJsonPath('stats.answerRate', 66.7)
                ->assertJsonPath('stats.slaPercent', 66.7)
                ->assertJsonPath('stats.callsByHour.10', 1)
                ->assertJsonPath('stats.callsByHour.11', 3)
                ->assertJsonPath('agents.0.calls_today', 3)
                ->assertJsonPath('agents.0.avg_handle', 150);
        } finally {
            Carbon::setTestNow();
        }
    }
}
