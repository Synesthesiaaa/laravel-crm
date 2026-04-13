<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\CampaignService;
use App\Services\Telephony\TelephonyBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class TelephonyBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_bootstrap_payload_uses_user_defaults_when_global_flag_on(): void
    {
        config(['vicidial.auto_bootstrap_on_crm_login' => true]);

        $campaignService = Mockery::mock(CampaignService::class);
        $campaignService->shouldReceive('getCampaigns')->andReturn([
            'mbsales' => ['name' => 'Main'],
            'other' => ['name' => 'Other'],
        ]);

        $service = new TelephonyBootstrapService($campaignService);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'agent1',
            'vici_pass' => 'secret',
            'extension' => '6001',
            'auto_vici_login' => true,
            'default_blended' => false,
            'default_ingroups' => 'SALES  SUPPORT',
            'default_campaign' => 'other',
        ]);

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('campaign', 'mbsales');

        $service->storeBootstrapPayload($request, $user);

        $this->assertSame([
            'campaign' => 'mbsales',
            'phone_login' => '6001',
            'blended' => false,
            'ingroups' => ['SALES', 'SUPPORT'],
        ], $request->session()->get('telephony_bootstrap'));
    }

    public function test_store_bootstrap_uses_default_campaign_when_session_campaign_invalid(): void
    {
        config(['vicidial.auto_bootstrap_on_crm_login' => true]);

        $campaignService = Mockery::mock(CampaignService::class);
        $campaignService->shouldReceive('getCampaigns')->andReturn([
            'mbsales' => ['name' => 'Main'],
            'other' => ['name' => 'Other'],
        ]);

        $service = new TelephonyBootstrapService($campaignService);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'agent1',
            'vici_pass' => 'secret',
            'extension' => '6001',
            'auto_vici_login' => true,
            'default_campaign' => 'other',
        ]);

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('campaign', 'unknown-code');

        $service->storeBootstrapPayload($request, $user);

        $payload = $request->session()->get('telephony_bootstrap');
        $this->assertSame('other', $payload['campaign']);
    }

    public function test_store_bootstrap_payload_applies_for_non_agent_roles_when_configured(): void
    {
        config(['vicidial.auto_bootstrap_on_crm_login' => true]);

        $campaignService = Mockery::mock(CampaignService::class);
        $campaignService->shouldReceive('getCampaigns')->andReturn([
            'mbsales' => ['name' => 'Main'],
        ]);

        $service = new TelephonyBootstrapService($campaignService);

        $user = User::factory()->create([
            'role' => 'Admin',
            'vici_user' => 'admin1',
            'vici_pass' => 'secret',
            'extension' => '7001',
            'auto_vici_login' => true,
            'default_campaign' => 'mbsales',
        ]);

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $service->storeBootstrapPayload($request, $user);

        $payload = $request->session()->get('telephony_bootstrap');
        $this->assertNotNull($payload);
        $this->assertSame('mbsales', $payload['campaign']);
        $this->assertSame('7001', $payload['phone_login']);
    }
}
