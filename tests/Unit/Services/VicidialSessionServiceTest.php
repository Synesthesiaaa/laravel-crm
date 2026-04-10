<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Models\VicidialServer;
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

        $this->agentApiMock = Mockery::mock(VicidialProxyService::class);
        $this->nonAgentApiMock = Mockery::mock(VicidialNonAgentApiService::class);
        $this->service = new VicidialSessionService($this->agentApiMock, $this->nonAgentApiMock);

        $this->user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
            'sip_password' => 'sippass',
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

    public function test_login_fails_when_phone_login_missing(): void
    {
        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'agentx',
            'vici_pass' => 'passx',
            'extension' => null,
        ]);

        $result = $this->service->loginAgent($user, 'testcamp', null, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsStringIgnoringCase('phone login is required', $result->message);
    }

    public function test_login_fails_when_agent_api_rejects_credentials(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::any())
            ->andReturn([
                'success' => false,
                'raw_response' => 'ERROR: INVALID USERNAME/PASSWORD',
                'message' => 'VICIdial credentials were rejected.',
            ]);

        $result = $this->service->loginAgent($this->user, 'testcamp');

        $this->assertFalse($result->success);
        $this->assertStringContainsStringIgnoringCase('rejected', $result->message);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'login_failed',
        ]);
    }

    public function test_login_succeeds_with_pending_status_and_iframe_url(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::any())
            ->andReturn([
                'success' => true,
                'raw_response' => 'SUCCESS: agent logged in',
                'message' => null,
            ]);

        VicidialServer::factory()->create([
            'campaign_code' => 'testcamp',
            'api_url' => 'https://vici.example.com/agc/api.php',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->andReturn(VicidialServer::first());

        $result = $this->service->loginAgent($this->user, 'testcamp');

        $this->assertTrue($result->success);
        // After login, session must be pending – not yet ready (needs iframe verification).
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'login_pending',
        ]);
        // iframe_url must be present in data.
        $this->assertNotNull($result->data['iframe_url'] ?? null);
        $this->assertStringContainsString('phone_login=6001', $result->data['iframe_url']);
        $this->assertStringContainsString('VD_login=testagent', $result->data['iframe_url']);
        $this->assertStringContainsString('relogin=YES', $result->data['iframe_url']);
        $this->assertSame('testagent', $result->data['iframe_alignment']['vd_login'] ?? null);
        $this->assertSame('testcamp', $result->data['iframe_alignment']['vd_campaign'] ?? null);
        $this->assertSame('6001', $result->data['iframe_alignment']['phone_login'] ?? null);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'last_iframe_url' => $result->data['iframe_url'],
        ]);
    }

    public function test_login_uses_vd_overrides_for_agent_api_and_iframe_alignment(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::on(function (array $params): bool {
                $creds = (array) ($params['credentials'] ?? []);

                return ($creds['vici_user'] ?? null) === '9999'
                    && ($creds['vici_pass'] ?? null) === 'typedpass';
            }))
            ->andReturn([
                'success' => true,
                'raw_response' => 'SUCCESS: agent logged in',
                'message' => null,
            ]);

        VicidialServer::factory()->create([
            'campaign_code' => 'testcamp',
            'api_url' => 'https://vici.example.com/agc/api.php',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->andReturn(VicidialServer::first());

        $result = $this->service->loginAgent(
            $this->user,
            'testcamp',
            '6001',
            'sippass',
            true,
            [],
            '9999',
            'typedpass',
        );

        $this->assertTrue($result->success);
        $this->assertSame('9999', $result->data['iframe_alignment']['vd_login'] ?? null);
        $this->assertStringContainsString('VD_login=9999', (string) ($result->data['iframe_url'] ?? ''));
        $this->assertStringContainsString('VD_pass=typedpass', (string) ($result->data['iframe_url'] ?? ''));
    }

    public function test_login_pending_when_api_unreachable(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'login', Mockery::any())
            ->andReturn([
                'success' => false,
                'raw_response' => '',
                'message' => 'Connection timed out',
            ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->andReturn(null);

        $result = $this->service->loginAgent($this->user, 'testcamp');

        // Network errors must NOT hard-fail – iframe can still recover.
        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'login_pending',
        ]);
    }

    // ── verifyLogin ───────────────────────────────────────────────────────────

    public function test_verify_login_iframe_agent_api_only_skips_non_agent_and_promotes(): void
    {
        config(['vicidial.session_iframe_agent_api_only' => true]);

        VicidialAgentSession::factory()->create([
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'login_pending',
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->with('testcamp')
            ->andReturn(null);
        $this->nonAgentApiMock->shouldNotReceive('execute');

        $result = $this->service->verifyLogin($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertSame('ready', $result->data['login_state'] ?? '');
        $this->assertTrue($result->data['iframe_trust_mode'] ?? false);
        $this->assertFalse($result->data['non_agent_live_check_skipped'] ?? true);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'ready',
        ]);
    }

    public function test_verify_login_iframe_skips_non_agent_when_skip_config_enabled(): void
    {
        config(['vicidial.session_iframe_agent_api_only' => true]);
        config(['vicidial.session_iframe_confirm_non_agent_live' => true]);
        config(['vicidial.session_iframe_skip_non_agent_live_check' => true]);

        VicidialAgentSession::factory()->create([
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'login_pending',
        ]);

        $this->nonAgentApiMock->shouldNotReceive('getServerForCampaign');
        $this->nonAgentApiMock->shouldNotReceive('execute');

        $result = $this->service->verifyLogin($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['non_agent_live_check_skipped'] ?? false);
        $this->assertStringContainsString('skipped', strtolower($result->message ?? ''));
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'ready',
        ]);
    }

    public function test_verify_login_marks_session_ready_when_agent_live(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'login_pending',
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('execute')
            ->andReturn(OperationResult::success([
                'raw_response' => 'STATUS|ACTIVE|6001|testcamp',
                'rows' => [['STATUS', 'ACTIVE', '6001', 'testcamp']],
            ]));

        $result = $this->service->verifyLogin($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertEquals('ready', $result->data['login_state'] ?? '');
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'ready',
        ]);
    }

    public function test_verify_login_iframe_non_agent_mismatch_keeps_pending_without_hard_fail(): void
    {
        config(['vicidial.session_iframe_agent_api_only' => true]);
        config(['vicidial.session_iframe_confirm_non_agent_live' => true]);
        config(['vicidial.session_iframe_skip_non_agent_live_check' => false]);

        $server = VicidialServer::factory()->create([
            'campaign_code' => 'testcamp',
            'api_url' => 'https://vici.example.com/agc/api.php',
            'api_user' => 'apiu',
            'api_pass' => 'apip',
            'is_active' => true,
            'is_default' => true,
        ]);

        VicidialAgentSession::factory()->create([
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'login_pending',
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->with('testcamp')
            ->andReturn($server);
        $this->nonAgentApiMock
            ->shouldReceive('execute')
            ->andReturn(OperationResult::failure('ERROR: agent_status AGENT NOT LOGGED IN: 9999|9999'));

        $result = $this->service->verifyLogin($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertSame('login_pending', $result->data['login_state'] ?? null);
        $this->assertFalse((bool) ($result->data['stop_verify_poll'] ?? true));
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'login_pending',
        ]);
    }

    // ── buildIframeUrl ────────────────────────────────────────────────────────

    public function test_build_iframe_url_uses_canonical_params(): void
    {
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'testcamp',
            'api_url' => 'https://vici.example.com/agc/api.php',
            'is_active' => true,
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->once()
            ->with('testcamp')
            ->andReturn($server);

        $url = $this->service->buildIframeUrl($this->user, 'testcamp', '6001', 'sippass');

        $this->assertNotNull($url);
        $this->assertStringContainsString('agc/vicidial.php', $url);
        $this->assertStringContainsString('phone_login=6001', $url);
        $this->assertStringContainsString('phone_pass=sippass', $url);
        $this->assertStringContainsString('VD_login=testagent', $url);
        $this->assertStringContainsString('VD_pass=testpass', $url);
        $this->assertStringContainsString('VD_campaign=testcamp', $url);
        $this->assertStringContainsString('relogin=YES', $url);
        // Must NOT contain vici_user as phone_login fallback
        $this->assertStringNotContainsString('phone_login=testagent', $url);
    }

    public function test_build_iframe_url_returns_null_when_no_server(): void
    {
        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->once()
            ->andReturn(null);

        $url = $this->service->buildIframeUrl($this->user, 'testcamp', '6001', 'sippass');

        $this->assertNull($url);
    }

    public function test_resolve_effective_phone_credentials_prefers_explicit_values(): void
    {
        $creds = $this->service->resolveEffectivePhoneCredentials($this->user, '7000', 'custompass');

        $this->assertSame('7000', $creds['phone_login']);
        $this->assertSame('custompass', $creds['phone_pass']);
    }

    public function test_resolve_effective_phone_credentials_falls_back_to_user_extension_and_sip(): void
    {
        $creds = $this->service->resolveEffectivePhoneCredentials($this->user, null, null);

        $this->assertSame('6001', $creds['phone_login']);
        $this->assertSame('sippass', $creds['phone_pass']);
    }

    public function test_get_aligned_iframe_url_matches_login_without_phone_overrides(): void
    {
        VicidialAgentSession::factory()->create([
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'phone_login' => '6001',
            'session_status' => 'login_pending',
        ]);

        $server = VicidialServer::factory()->create([
            'campaign_code' => 'testcamp',
            'api_url' => 'https://vici.example.com/agc/api.php',
            'is_active' => true,
        ]);

        $this->nonAgentApiMock
            ->shouldReceive('getServerForCampaign')
            ->with('testcamp')
            ->andReturn($server);

        $fromHelper = $this->service->getAlignedIframeUrlForCampaign($this->user, 'testcamp');
        $fromBuild = $this->service->buildIframeUrl($this->user, 'testcamp', '6001', 'sippass');

        $this->assertNotNull($fromHelper);
        $this->assertSame($fromBuild, $fromHelper);
    }

    public function test_get_aligned_iframe_url_returns_null_without_phone_login(): void
    {
        $userNoExt = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'x',
            'vici_pass' => 'y',
            'extension' => null,
            'sip_password' => 'z',
        ]);

        VicidialAgentSession::factory()->create([
            'user_id' => $userNoExt->id,
            'campaign_code' => 'testcamp',
            'phone_login' => null,
            'session_status' => 'login_pending',
        ]);

        $this->assertNull($this->service->getAlignedIframeUrlForCampaign($userNoExt, 'testcamp'));
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
            'user_id' => $this->user->id,
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
            'user_id' => $this->user->id,
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
            'user_id' => $this->user->id,
            'campaign_code' => 'testcamp',
            'session_status' => 'ready',
            'last_iframe_url' => 'https://vici.example.com/agc/vicidial.php?x=1',
        ]);

        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->with($this->user, 'testcamp', 'logout', ['value' => 'LOGOUT'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);

        $result = $this->service->logoutAgent($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'logged_out',
            'last_iframe_url' => null,
        ]);
    }

    public function test_logout_still_marks_local_session_even_if_api_fails(): void
    {
        $this->agentApiMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['success' => false, 'raw_response' => '', 'message' => 'Timeout']);

        $result = $this->service->logoutAgent($this->user, 'testcamp');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $this->user->id,
            'session_status' => 'logged_out',
        ]);
    }
}
