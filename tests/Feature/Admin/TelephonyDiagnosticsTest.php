<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\TelephonyDiagnosticsCampaignMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelephonyDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostics_include_go_live_readiness_checks(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        config([
            'vicidial.api_url' => 'http://vicidial.test/agc/api.php',
            'vicidial.non_agent_api_url' => 'http://vicidial.test/non_agent_api.php',
            'asterisk.host' => '',
            'asterisk.secret' => '',
            'asterisk.webhook_secret' => 'ami-secret',
            'vicidial.events_webhook_secret' => 'vici-secret',
            'vicidial.call_url_secret' => 'vici-secret',
            'broadcasting.default' => 'null',
        ]);

        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.configuration.telephony-diagnostics'));

        $response->assertOk()
            ->assertJsonPath('checks.7.label', 'Database Connection')
            ->assertJsonFragment(['label' => 'Migration Status'])
            ->assertJsonFragment(['label' => 'Required Tables'])
            ->assertJsonFragment(['label' => 'Webhook Secrets'])
            ->assertJsonFragment(['label' => 'Broadcasting Config']);
    }

    public function test_diagnostics_include_paste_ready_vicidial_call_url_links(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        config([
            'app.url' => 'http://crm.test',
            'vicidial.api_url' => 'http://vicidial.test/agc/api.php',
            'vicidial.non_agent_api_url' => 'http://vicidial.test/non_agent_api.php',
            'asterisk.host' => '',
            'asterisk.secret' => '',
            'asterisk.webhook_secret' => 'ami-secret',
            'vicidial.events_webhook_secret' => 'vici-secret',
            'vicidial.call_url_secret' => 'vicidial-secret',
            'broadcasting.default' => 'null',
        ]);

        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.configuration.telephony-diagnostics'));

        $response->assertOk();

        $links = collect($response->json('call_url_links'))->keyBy('key');

        $this->assertCount(5, $links);
        $this->assertSame('Start Call URL', $links['start_call']['label']);
        $this->assertSame('Dispo Call URL', $links['dispo_call']['label']);
        $this->assertSame('No Agent Call URL', $links['no_agent_call']['label']);
        $this->assertSame('Dead Call Trigger URL', $links['dead_call_trigger']['label']);
        $this->assertSame('Pause Max URL', $links['pause_max']['label']);

        $this->assertStringStartsWith('VAR'.route('api.webhooks.vicidial.start-call', ['sig' => 'vicidial-secret']), $links['start_call']['url']);
        $this->assertStringContainsString('sig=vicidial-secret', $links['start_call']['url']);
        $this->assertStringContainsString('campaign=--A--campaign--B--', $links['start_call']['url']);
        $this->assertStringContainsString('lead_id=--A--lead_id--B--', $links['start_call']['url']);
        $this->assertStringContainsString('phone_number=--A--phone_number--B--', $links['start_call']['url']);

        $this->assertStringStartsWith('VAR'.route('api.webhooks.vicidial.dispo-call', ['sig' => 'vicidial-secret']), $links['dispo_call']['url']);
        $this->assertStringContainsString('dispo=--A--dispo--B--', $links['dispo_call']['url']);
        $this->assertStringContainsString('talk_time=--A--talk_time--B--', $links['dispo_call']['url']);
        $this->assertStringContainsString('call_notes=--A--call_notes--B--', $links['dispo_call']['url']);
        $this->assertStringContainsString('callback_datetime=--A--callback_datetime--B--', $links['dispo_call']['url']);

        $this->assertStringStartsWith('VAR'.route('api.webhooks.vicidial.no-agent-call', ['sig' => 'vicidial-secret']), $links['no_agent_call']['url']);
        $this->assertStringContainsString('status=--A--status--B--', $links['no_agent_call']['url']);

        $this->assertStringStartsWith('VAR'.route('api.webhooks.vicidial.dead-call-trigger', ['sig' => 'vicidial-secret']), $links['dead_call_trigger']['url']);
        $this->assertStringContainsString('call_id=--A--call_id--B--', $links['dead_call_trigger']['url']);

        $this->assertStringStartsWith('VAR'.route('api.webhooks.vicidial.pause-max', ['sig' => 'vicidial-secret']), $links['pause_max']['url']);
        $this->assertStringContainsString('user=--A--user--B--', $links['pause_max']['url']);
        $this->assertStringContainsString('campaign=--A--campaign--B--', $links['pause_max']['url']);
    }

    public function test_campaign_server_mapping_check_uses_batched_reads(): void
    {
        Campaign::factory()->create(['code' => 'camp-a', 'name' => 'Campaign A']);
        Campaign::factory()->create(['code' => 'camp-b', 'name' => 'Campaign B']);
        Campaign::factory()->create(['code' => 'camp-c', 'name' => 'Campaign C']);

        VicidialServer::factory()->create([
            'campaign_code' => 'camp-a',
            'api_user' => 'api-user-a',
            'api_pass' => 'api-pass-a',
            'is_active' => true,
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'camp-b',
            'api_user' => '',
            'api_pass' => 'api-pass-b',
            'is_active' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $this->app->make(TelephonyDiagnosticsCampaignMappingService::class)
            ->checkCampaignServerMappings();

        $this->assertSame('warn', $result['status']);
        $this->assertStringContainsString('No active server for: camp-c', $result['message']);
        $this->assertStringContainsString('Missing Non-Agent API user/pass for: camp-b', $result['message']);
        $this->assertCount(2, DB::getQueryLog());
    }
}
