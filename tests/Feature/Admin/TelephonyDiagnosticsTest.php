<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
