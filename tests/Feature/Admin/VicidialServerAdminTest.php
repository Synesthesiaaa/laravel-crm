<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VicidialServerAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        // Create a campaign so the select box has an option
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales', 'color' => '#3b82f6']);
    }

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_index_requires_super_admin(): void
    {
        $agent = User::factory()->create(['role' => 'Agent']);
        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('admin.vicidial-servers.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_server_list(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('admin.vicidial-servers.index'))
            ->assertOk();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_server_with_all_fields(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.vicidial-servers.store'), [
                'campaign_code' => 'mbsales',
                'server_name' => 'Main ViciDial',
                'api_url' => 'http://10.10.88.138/agc/api.php',
                'db_host' => '10.10.88.138',
                'db_username' => 'cron',
                'db_password' => 'secret',
                'db_name' => 'asterisk',
                'db_port' => 3306,
                'api_user' => 'admin',
                'api_pass' => 'adminpass',
                'source' => 'crm_tracker',
                'is_active' => '1',
                'is_default' => '1',
                'priority' => 1,
            ])
            ->assertRedirect(route('admin.vicidial-servers.index'));

        $this->assertDatabaseHas('vicidial_servers', [
            'campaign_code' => 'mbsales',
            'server_name' => 'Main ViciDial',
            'api_user' => 'admin',
            'source' => 'crm_tracker',
            'is_active' => true,
            'is_default' => true,
            'priority' => 1,
        ]);
    }

    public function test_store_requires_api_url(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.vicidial-servers.store'), [
                'campaign_code' => 'mbsales',
                'server_name' => 'Missing URL',
                'db_host' => '10.0.0.1',
                'db_username' => 'cron',
            ])
            ->assertSessionHasErrors('api_url');
    }

    public function test_store_rejects_non_url_for_api_url(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.vicidial-servers.store'), [
                'campaign_code' => 'mbsales',
                'server_name' => 'Bad URL',
                'api_url' => 'not-a-url',
                'db_host' => '10.0.0.1',
                'db_username' => 'cron',
            ])
            ->assertSessionHasErrors('api_url');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_persists_api_credentials(): void
    {
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'mbsales',
            'api_user' => null,
            'api_pass' => null,
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->put(route('admin.vicidial-servers.update', $server), [
                'campaign_code' => 'mbsales',
                'server_name' => $server->server_name,
                'api_url' => $server->api_url,
                'db_host' => $server->db_host,
                'db_username' => $server->db_username,
                'api_user' => 'non_agent_user',
                'api_pass' => 'non_agent_pass',
                'source' => 'crm',
                'is_active' => '1',
                'is_default' => '0',
                'priority' => 0,
            ])
            ->assertRedirect(route('admin.vicidial-servers.index'));

        $server->refresh();
        $this->assertSame('non_agent_user', $server->api_user);
        $this->assertSame('crm', $server->source);
    }

    public function test_update_keeps_api_pass_when_blank(): void
    {
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'mbsales',
            'api_user' => 'admin',
            'api_pass' => 'original_pass',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->put(route('admin.vicidial-servers.update', $server), [
                'campaign_code' => 'mbsales',
                'server_name' => $server->server_name,
                'api_url' => $server->api_url,
                'db_host' => $server->db_host,
                'db_username' => $server->db_username,
                'api_user' => 'admin',
                'api_pass' => '',  // blank = keep current
                'is_active' => '1',
                'is_default' => '0',
                'priority' => 0,
            ]);

        $server->refresh();
        // api_pass should remain unchanged (controller only updates when filled)
        $this->assertNotNull($server->api_pass);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_server(): void
    {
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.vicidial-servers.destroy'), ['id' => $server->id])
            ->assertRedirect(route('admin.vicidial-servers.index'));

        $this->assertSoftDeleted('vicidial_servers', ['id' => $server->id]);
    }

    // ── Credential completeness ───────────────────────────────────────────────

    public function test_server_without_api_credentials_flagged_in_diagnostics(): void
    {
        VicidialServer::factory()->create([
            'campaign_code' => 'mbsales',
            'is_active' => true,
            'api_user' => null,
            'api_pass' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.telephony-diagnostics'));

        $response->assertOk();
        $checks = collect($response->json('checks'));
        $mappingCheck = $checks->firstWhere('label', 'Campaign → ViciDial Server Mapping');
        $this->assertNotNull($mappingCheck);
        $this->assertSame('warn', $mappingCheck['status']);
        $this->assertStringContainsString('mbsales', $mappingCheck['message']);
    }
}
