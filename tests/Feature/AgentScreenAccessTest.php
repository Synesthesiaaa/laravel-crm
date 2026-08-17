<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_disabled_agent_screen_access_hides_links_and_blocks_direct_access(): void
    {
        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('agent.index').'"', false);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->getJson(route('api.search', ['q' => 'agent']))
            ->assertOk()
            ->assertJsonMissing(['title' => 'Agent Screen']);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertForbidden();

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent-webforms.show', ['campaign' => 'mbsales']))
            ->assertForbidden();

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->postJson(route('api.agent.capture'), [])
            ->assertForbidden()
            ->assertJsonPath('feature', 'agent_screen_access');
    }

    public function test_enabled_agent_screen_access_preserves_navigation_search_and_page_access(): void
    {
        $this->enableAgentScreenAccess();

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('href="'.route('agent.index').'"', false);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->getJson(route('api.search', ['q' => 'agent']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Agent Screen',
                'subtitle' => null,
                'url' => route('agent.index'),
            ]);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertOk();
    }

    private function enableAgentScreenAccess(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
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
