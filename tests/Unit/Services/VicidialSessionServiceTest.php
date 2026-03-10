<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Services\Telephony\VicidialNonAgentApiService;
use App\Services\Telephony\VicidialProxyService;
use App\Services\Telephony\VicidialSessionService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VicidialSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private VicidialProxyService $agentApiMock;
    private VicidialNonAgentApiService $nonAgentApiMock;
    private VicidialSessionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agentApiMock    = Mockery::mock(VicidialProxyService::class);
        $this->nonAgentApiMock = Mockery::mock(VicidialNonAgentApiService::class);
        $this->service         = new VicidialSessionService($this->agentApiMock, $this->nonAgentApiMock);

        $this->user = User::factory()->create([
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

    // ── loginAgent ────────────────────────────────────────────────────────────

    public function test_login_fails_when_vici_credentials_missing(): void
    {
        $user = User::factory()->create(['role' => 'Agent', 'vici_user' => null, 'vici_pass' => null]);

        $result = $this->service->loginAgent($user, 'testcamp');

        $this->assertFalse($result->success);
        $this->assertStringContainsStringIgnoringCase('credentials are not set', $result->message);
    }

    public function test_login_fails_when_agent_api_rejects_credentials(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::any())
            ->andReturn([
                'success'      => false,
                'raw_response' => 'ERROR: INVALID USERNAME/PASSWORD',
                'message'      => 'VICIdial credentials were rejected.',
            ]);

        $result = $this->service->loginAgent($this->user, 'testcamp');

        $this->assertFalse($result->success);
        $this->assertStringContainsStringIgnoringCase('rejected', $result->message);
    }

    public function test_login_succeeds_and_creates_local_session(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::any())
            ->andReturn([
                'success'      => true,
                'raw_response' => 'SUCCESS: agent logged in',
                'message'      => null,
            ]);

        $this->nonAgentApiMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn(OperationResult::success(['raw_response' => 'SUCCESS', 'rows' => []]));

        $result = $this->service->loginAgent($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->user->id,
            'campaign_code'  => 'testcamp',
            'session_status' => 'ready',
        ]);
    }

    public function test_login_partial_when_api_unreachable_but_credentials_not_rejected(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::any())
            ->andReturn([
                'success'      => false,
                'raw_response' => '',
                'message'      => 'Connection timed out',
            ]);

        $this->nonAgentApiMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn(OperationResult::failure('Connection refused'));

        $result = $this->service->loginAgent($this->user, 'testcamp');

        // Should NOT hard-fail – partial login is acceptable when ViciDial is unreachable
        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->user->id,
            'session_status' => 'ready_partial',
        ]);
    }

    // ── pauseAgent ────────────────────────────────────────────────────────────

    public function test_pause_agent_sends_external_pause(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'external_pause', ['value' => 'PAUSE'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $result = $this->service->pauseAgent($this->user, 'testcamp', 'PAUSE');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->user->id,
            'session_status' => 'paused',
        ]);
    }

    public function test_resume_agent_updates_status_to_ready(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $result = $this->service->pauseAgent($this->user, 'testcamp', 'RESUME');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->user->id,
            'session_status' => 'ready',
        ]);
    }

    public function test_pause_agent_rejects_invalid_value(): void
    {
        $result = $this->service->pauseAgent($this->user, 'testcamp', 'INVALID');

        $this->assertFalse($result->success);
    }

    // ── setPauseCode ──────────────────────────────────────────────────────────

    public function test_set_pause_code_succeeds_when_agent_already_paused(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'pause_code', ['value' => 'BREAK'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $result = $this->service->setPauseCode($this->user, 'testcamp', 'BREAK');

        $this->assertTrue($result->success);
    }

    public function test_set_pause_code_auto_pauses_then_retries_when_not_paused(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'pause_code', ['value' => 'LUNCH'])
            ->andReturn(['success' => false, 'raw_response' => 'ERROR: not paused', 'message' => 'not paused']);

        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'external_pause', ['value' => 'PAUSE'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'pause_code', ['value' => 'LUNCH'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $result = $this->service->setPauseCode($this->user, 'testcamp', 'LUNCH');

        $this->assertTrue($result->success);
    }

    // ── logoutAgent ───────────────────────────────────────────────────────────

    public function test_logout_agent_marks_session_logged_out(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id'        => $this->user->id,
            'campaign_code'  => 'testcamp',
            'session_status' => 'ready',
        ]);

        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'logout', ['value' => 'LOGOUT'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $result = $this->service->logoutAgent($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->user->id,
            'session_status' => 'logged_out',
        ]);
    }

    public function test_logout_still_marks_local_session_even_if_api_fails(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['success' => false, 'raw_response' => '', 'message' => 'Timeout']);

        $result = $this->service->logoutAgent($this->user, 'testcamp');

        // Should still succeed locally
        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id'        => $this->user->id,
            'session_status' => 'logged_out',
        ]);
    }
}
