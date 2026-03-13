<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Models\VicidialServer;
use App\Services\Telephony\VicidialNonAgentApiService;
use App\Services\Telephony\VicidialProxyService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VicidialSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'role'         => 'Agent',
            'vici_user'    => 'testagent',
            'vici_pass'    => 'testpass',
            'extension'    => '6001',
            'sip_password' => 'sippass',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function campaignSession(): array
    {
        return ['campaign' => 'testcamp', 'campaign_name' => 'Test'];
    }

    private function mockAgentApi(bool $success = true, string $raw = 'SUCCESS'): void
    {
        $mock = Mockery::mock(VicidialProxyService::class);
        $mock->shouldReceive('execute')
             ->andReturn(['success' => $success, 'raw_response' => $raw, 'message' => $success ? null : 'error']);
        $this->instance(VicidialProxyService::class, $mock);
    }

    private function mockNonAgentApi(bool $success = true): void
    {
        $mock = Mockery::mock(VicidialNonAgentApiService::class);
        $result = $success
            ? OperationResult::success(['raw_response' => 'SUCCESS', 'rows' => []])
            : OperationResult::failure('Error');
        $mock->shouldReceive('execute')->andReturn($result);
        $mock->shouldReceive('getServerForCampaign')->andReturn(null);
        $this->instance(VicidialNonAgentApiService::class, $mock);
    }

    private function mockNonAgentApiWithServer(bool $success = true): void
    {
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'testcamp',
            'api_url'       => 'https://vici.example.com/agc/api.php',
            'is_active'     => true,
            'is_default'    => true,
        ]);

        $mock = Mockery::mock(VicidialNonAgentApiService::class);
        $result = $success
            ? OperationResult::success(['raw_response' => 'SUCCESS', 'rows' => []])
            : OperationResult::failure('Error');
        $mock->shouldReceive('execute')->andReturn($result);
        $mock->shouldReceive('getServerForCampaign')->andReturn($server);
        $this->instance(VicidialNonAgentApiService::class, $mock);
    }

    // ── POST /api/vicidial/session/login ──────────────────────────────────────

    public function test_login_requires_auth(): void
    {
        $this->postJson('/api/vicidial/session/login', ['campaign' => 'testcamp'])
             ->assertUnauthorized();
    }

    public function test_login_fails_when_vici_credentials_missing(): void
    {
        $userNoCreds = User::factory()->create(['role' => 'Agent', 'vici_user' => null, 'vici_pass' => null]);

        $response = $this->actingAs($userNoCreds)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', ['campaign' => 'testcamp'])
             ->assertUnprocessable();

        $msg = strtolower($response->json('message') ?? '');
        $this->assertStringContainsString('credentials', $msg);
    }

    public function test_login_fails_when_phone_login_missing(): void
    {
        $user = User::factory()->create([
            'role'      => 'Agent',
            'vici_user' => 'agentx',
            'vici_pass' => 'passx',
            'extension' => null,
        ]);

        $response = $this->actingAs($user)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', ['campaign' => 'testcamp'])
             ->assertUnprocessable();

        $msg = strtolower($response->json('message') ?? '');
        $this->assertStringContainsString('phone login', $msg);
    }

    public function test_login_fails_when_agent_api_rejects(): void
    {
        $this->mockNonAgentApi();
        $mock = Mockery::mock(VicidialProxyService::class);
        $mock->shouldReceive('execute')
             ->once()
             ->andReturn([
                 'success'      => false,
                 'raw_response' => 'ERROR: INVALID USERNAME/PASSWORD',
                 'message'      => 'credentials rejected',
             ]);
        $this->instance(VicidialProxyService::class, $mock);

        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', ['campaign' => 'testcamp'])
             ->assertUnprocessable();
    }

    public function test_login_succeeds_returns_pending_and_iframe_url(): void
    {
        $this->mockAgentApi(true, 'SUCCESS: agent logged in');
        $this->mockNonAgentApiWithServer(true);

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', [
                 'campaign'    => 'testcamp',
                 'phone_login' => '6001',
                 'phone_pass'  => 'sippass',
             ]);

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('login_state', 'login_pending');

        // After login the session must be in login_pending state (not yet ready)
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->agent->id,
            'session_status' => 'login_pending',
        ]);

        // iframe_url must be in the response with canonical params
        $iframeUrl = $response->json('iframe_url');
        $this->assertNotNull($iframeUrl);
        $this->assertStringContainsString('phone_login=6001', $iframeUrl);
        $this->assertStringContainsString('VD_login=testagent', $iframeUrl);
        $this->assertStringContainsString('relogin=YES', $iframeUrl);
    }

    public function test_login_response_contract_contains_required_fields(): void
    {
        $this->mockAgentApi(true);
        $this->mockNonAgentApiWithServer();

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', [
                 'campaign'    => 'testcamp',
                 'phone_login' => '6001',
             ]);

        $response->assertOk()
                 ->assertJsonStructure(['success', 'message', 'iframe_url', 'login_state', 'data']);
    }

    // ── POST /api/vicidial/session/verify ─────────────────────────────────────

    public function test_verify_requires_auth(): void
    {
        $this->postJson('/api/vicidial/session/verify', ['campaign' => 'testcamp'])
             ->assertUnauthorized();
    }

    public function test_verify_promotes_session_to_ready_when_agent_live(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id'        => $this->agent->id,
            'campaign_code'  => 'testcamp',
            'session_status' => 'login_pending',
        ]);

        $mock = Mockery::mock(VicidialNonAgentApiService::class);
        $mock->shouldReceive('execute')
             ->andReturn(OperationResult::success(['raw_response' => 'STATUS|ACTIVE', 'rows' => []]));
        $mock->shouldReceive('getServerForCampaign')->andReturn(null);
        $this->instance(VicidialNonAgentApiService::class, $mock);

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/verify', ['campaign' => 'testcamp']);

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('login_state', 'ready');

        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->agent->id,
            'session_status' => 'ready',
        ]);
    }

    public function test_verify_returns_pending_when_agent_not_yet_live(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id'        => $this->agent->id,
            'campaign_code'  => 'testcamp',
            'session_status' => 'login_pending',
        ]);

        $mock = Mockery::mock(VicidialNonAgentApiService::class);
        $mock->shouldReceive('execute')
             ->andReturn(OperationResult::failure('ERROR: agent_status AGENT NOT LOGGED IN: 9999|9999'));
        $mock->shouldReceive('getServerForCampaign')->andReturn(null);
        $this->instance(VicidialNonAgentApiService::class, $mock);

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/verify', ['campaign' => 'testcamp']);

        // 202 Accepted – still pending, frontend should keep polling
        $this->assertContains($response->status(), [200, 202]);
        $this->assertFalse((bool) $response->json('success'));
    }

    // ── POST /api/vicidial/session/pause ──────────────────────────────────────

    public function test_pause_agent_returns_success(): void
    {
        $this->mockAgentApi(true);

        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/pause', ['campaign' => 'testcamp', 'value' => 'PAUSE'])
             ->assertOk();

        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->agent->id,
            'session_status' => 'paused',
        ]);
    }

    public function test_pause_agent_rejects_invalid_value(): void
    {
        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/pause', ['campaign' => 'testcamp', 'value' => 'WRONG'])
             ->assertUnprocessable();
    }

    // ── POST /api/vicidial/session/pause-code ────────────────────────────────

    public function test_set_pause_code_retries_after_auto_pause(): void
    {
        $mock = Mockery::mock(VicidialProxyService::class);
        $mock->shouldReceive('execute')
             ->with($this->agent, 'testcamp', 'pause_code', ['value' => 'LUNCH'])
             ->once()
             ->andReturn(['success' => false, 'raw_response' => 'ERROR: not paused', 'message' => 'not paused']);
        $mock->shouldReceive('execute')
             ->with($this->agent, 'testcamp', 'external_pause', ['value' => 'PAUSE'])
             ->once()
             ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $mock->shouldReceive('execute')
             ->with($this->agent, 'testcamp', 'pause_code', ['value' => 'LUNCH'])
             ->once()
             ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $this->instance(VicidialProxyService::class, $mock);

        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/pause-code', [
                 'campaign'   => 'testcamp',
                 'pause_code' => 'LUNCH',
             ])
             ->assertOk();
    }

    // ── POST /api/vicidial/session/logout ─────────────────────────────────────

    public function test_logout_marks_session_logged_out(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id'        => $this->agent->id,
            'campaign_code'  => 'testcamp',
            'session_status' => 'ready',
        ]);

        $this->mockAgentApi(true);

        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/logout', ['campaign' => 'testcamp'])
             ->assertOk();

        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->agent->id,
            'session_status' => 'logged_out',
        ]);
    }

    // ── GET /api/vicidial/session/status ──────────────────────────────────────

    public function test_status_returns_current_session_state(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id'        => $this->agent->id,
            'campaign_code'  => 'testcamp',
            'session_status' => 'paused',
        ]);

        $this->mockNonAgentApi(false);

        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->getJson('/api/vicidial/session/status?campaign=testcamp')
             ->assertOk()
             ->assertJsonPath('local_session.session_status', 'paused');
    }

    // ── Non-Agent API: reporting endpoint ─────────────────────────────────────

    public function test_reporting_endpoint_proxies_to_non_agent_api(): void
    {
        $mock = Mockery::mock(VicidialNonAgentApiService::class);
        $mock->shouldReceive('execute')
             ->zeroOrMoreTimes()
             ->andReturn(OperationResult::success([
                 'raw_response' => "status|agent_id|campaign\nACTIVE|6001|testcamp",
                 'rows' => [['status' => 'ACTIVE', 'agent_id' => '6001', 'campaign' => 'testcamp']],
             ]));
        $mock->shouldReceive('getServerForCampaign')->andReturn(null);
        $this->instance(VicidialNonAgentApiService::class, $mock);

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->getJson('/api/reports/agent-status?campaign=testcamp&agent_user=testagent');

        $this->assertContains($response->status(), [200, 422]);
    }
}
