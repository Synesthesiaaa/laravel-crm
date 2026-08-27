<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignVicidialCampaignMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_resolver_keeps_live_and_historical_statuses_distinct(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'crm-a',
            'is_default' => true,
        ]);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'SALES_A',
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
            'is_enabled' => true,
        ]);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'SALES_STALE',
            'status' => CampaignVicidialMapping::STATUS_STALE,
            'is_enabled' => true,
        ]);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'SALES_DISABLED',
            'status' => CampaignVicidialMapping::STATUS_DISABLED,
            'is_enabled' => true,
        ]);

        $scope = app(CrmCampaignVicidialScopeResolver::class)->resolve($campaign);

        $this->assertSame(['SALES_A'], $scope->liveCampaignCodes());
        $this->assertSame(['SALES_A', 'SALES_STALE'], $scope->historicalCampaignCodes());
        $this->assertSame(['SALES_STALE'], $scope->narrowCampaignCodes('SALES_STALE'));
        $this->assertSame([], $scope->narrowCampaignCodes('SALES_DISABLED'));
    }

    public function test_empty_mapping_does_not_expand_to_all_campaigns(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a']);
        VicidialServer::factory()->create([
            'campaign_code' => 'crm-a',
            'is_default' => true,
        ]);

        $scope = app(CrmCampaignVicidialScopeResolver::class)->resolve($campaign);

        $this->assertSame([], $scope->liveCampaignCodes());
        $this->assertSame([], $scope->historicalCampaignCodes());
    }

    public function test_campaign_admin_page_exposes_the_accessible_mapping_controls(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a', 'name' => 'CRM A']);
        VicidialServer::factory()->create(['campaign_code' => $campaign->code, 'server_name' => 'VICIdial A']);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->get(route('admin.campaigns.index'))
            ->assertOk()
            ->assertSee('VICIdial campaign mapping')
            ->assertSee('Search VICIdial campaigns')
            ->assertSee('Select all')
            ->assertSee('Clear all')
            ->assertSee(':disabled="campaign.unavailable === true"', false)
            ->assertSee('At least one campaign is required.');
    }

    public function test_catalog_is_bound_to_the_selected_server_and_mapping_replacement_is_atomic(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'crm-a',
            'api_url' => 'https://vici-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
            'is_default' => true,
        ]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Http::fake([
            'https://vici-a.example/*' => Http::response(
                "campaign_id|campaign_name|active\nSALES_A|Sales A|Y\nSALES_B|Sales B|N\n",
                200,
            ),
        ]);

        $catalog = $this->actingAs($superAdmin)
            ->getJson(route('admin.campaigns.vicidial-campaigns', $campaign).'?server_id='.$server->id);

        $catalog->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'campaigns');

        $this->assertSame([], app(CrmCampaignVicidialScopeResolver::class)->resolve($campaign)->historicalCampaignCodes());

        $this->actingAs($superAdmin)
            ->put(route('admin.campaigns.vicidial-mapping.update', $campaign), [
                'vicidial_server_id' => $server->id,
                'vicidial_campaign_codes' => ['SALES_A', 'SALES_B'],
            ])
            ->assertRedirect(route('admin.campaigns.index'));

        $this->assertDatabaseCount('campaign_vicidial_mappings', 2);
        $this->assertDatabaseHas('campaign_vicidial_mappings', [
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'SALES_B',
            'status' => CampaignVicidialMapping::STATUS_DISABLED,
        ]);
        $this->assertSame(['SALES_A'], app(CrmCampaignVicidialScopeResolver::class)->resolve($campaign)->historicalCampaignCodes());
    }

    public function test_mapping_rejects_a_server_owned_by_another_crm_campaign(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a']);
        $otherCampaign = Campaign::factory()->create(['code' => 'crm-b']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => $otherCampaign->code,
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->from(route('admin.campaigns.index'))
            ->put(route('admin.campaigns.vicidial-mapping.update', $campaign), [
                'vicidial_server_id' => $server->id,
                'vicidial_campaign_codes' => ['SALES_A'],
            ])
            ->assertRedirect(route('admin.campaigns.index'))
            ->assertSessionHasErrors('vicidial_server_id');

        $this->assertDatabaseCount('campaign_vicidial_mappings', 0);
    }

    public function test_mapping_requires_at_least_one_selected_campaign(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'crm-a']);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->from(route('admin.campaigns.index'))
            ->put(route('admin.campaigns.vicidial-mapping.update', $campaign), [
                'vicidial_server_id' => $server->id,
                'vicidial_campaign_codes' => [],
            ])
            ->assertRedirect(route('admin.campaigns.index'))
            ->assertSessionHasErrors('vicidial_campaign_codes');
    }

    public function test_catalog_failure_returns_safe_diagnostics_without_persisting_mappings(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'crm-a']);
        $server = VicidialServer::factory()->create([
            'campaign_code' => 'crm-a',
            'api_url' => 'https://vici-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-pass',
        ]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Http::fake(['https://vici-a.example/*' => Http::response('ERROR: permission denied', 403)]);

        $response = $this->actingAs($superAdmin)
            ->getJson(route('admin.campaigns.vicidial-campaigns', $campaign).'?server_id='.$server->id);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('campaigns', []);
        $this->assertDatabaseCount('campaign_vicidial_mappings', 0);
    }
}
