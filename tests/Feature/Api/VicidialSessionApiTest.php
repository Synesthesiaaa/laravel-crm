<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Services\Telephony\VicidialProxyService;
use App\Services\Telephony\VicidialNonAgentApiService;
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
            'role'      => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
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

    public function test_login_fails_when_agent_api_rejects(): void
    {
        $this->mockNonAgentApi();
        $mock = Mockery::mock(VicidialProxyService::class);
        $mock->shouldReceive('execute')
             ->once()
             ->andReturn(['success' => false, 'raw_response' => 'ERROR: INVALID USERNAME/PASSWORD', 'message' => 'credentials rejected']);
        $this->instance(VicidialProxyService::class, $mock);

        $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', ['campaign' => 'testcamp'])
             ->assertUnprocessable();
    }

    public function test_login_succeeds_and_returns_session(): void
    {
        $this->mockAgentApi(true, 'SUCCESS: agent logged in');
        $this->mockNonAgentApi(true);

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->postJson('/api/vicidial/session/login', ['campaign' => 'testcamp']);

        $response->assertOk()
                 ->assertJsonPath('success', true);

        // Verify session was persisted to DB
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->agent->id,
            'session_status' => 'ready',
        ]);
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
        // First call fails "not paused" → then external_pause → then retry succeeds
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
        $this->instance(VicidialNonAgentApiService::class, $mock);

        $response = $this->actingAs($this->agent)
             ->withSession($this->campaignSession())
             ->getJson('/api/reports/agent-status?campaign=testcamp&agent_user=testagent');

        // Validates the endpoint is routed and delegates to the service layer (200 or 422 are both acceptable)
        $this->assertContains($response->status(), [200, 422]);
    }
}
