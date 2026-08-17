<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentScreenAccessConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_super_admin_can_enable_agent_screen_access(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('admin.configuration', ['tab' => 'telephony']))
            ->assertOk()
            ->assertSee('Agent Screen Access', false)
            ->assertSee('features[agent_screen_access]', false);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.telephony-features.update'), [
                'features' => ['agent_screen_access' => '1'],
            ])
            ->assertRedirect(route('admin.configuration', ['tab' => 'telephony']));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);
    }

    public function test_super_admin_can_disable_agent_screen_access(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.telephony-features.update'), [
                'features' => [],
            ])
            ->assertRedirect(route('admin.configuration', ['tab' => 'telephony']));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '0',
        ]);
    }

    public function test_non_super_admin_cannot_change_agent_screen_access(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.telephony-features.update'), [
                'features' => ['agent_screen_access' => '1'],
            ])
            ->assertForbidden();
    }

    public function test_disabled_agent_screen_configuration_links_are_hidden_from_super_admin_dashboard(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.agent-screen.index').'"', false)
            ->assertDontSee('Agent Screen Cfg', false);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->getJson(route('api.search', ['q' => 'agent']))
            ->assertOk()
            ->assertJsonMissing(['title' => 'Agent Screen']);
    }

    public function test_enabled_agent_screen_configuration_links_are_visible_to_super_admin(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.agent-screen.index').'"', false)
            ->assertSee('Agent Screen Cfg', false);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->getJson(route('api.search', ['q' => 'agent']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Agent Screen',
                'subtitle' => null,
                'url' => route('agent.index'),
            ]);
    }

    /**
     * @return array{campaign: string, campaign_name: string}
     */
    private function campaignSession(): array
    {
        return [
            'campaign' => 'mbsales',
            'campaign_name' => 'MB Sales',
        ];
    }
}
