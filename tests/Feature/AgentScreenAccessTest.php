<?php

namespace Tests\Feature;

use App\Models\AgentScreenField;
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

        $response = $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertOk();

        $response->assertSee('id="agent-tool-panels"', false);
        $response->assertSee('role="tablist"', false);
        $response->assertSee('id="agent-tool-tab-transfer"', false);
        $response->assertSee('Choose a call tool', false);
    }

    public function test_agent_screen_primary_call_controls_have_explicit_accessible_names(): void
    {
        $this->enableAgentScreenAccess();

        $html = $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button(?=[^>]*class="[^"]*phone-dial-btn[^"]*")(?=[^>]*aria-label="[^"]+")[^>]*>/i',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<button(?=[^>]*class="[^"]*phone-hangup-btn[^"]*")(?=[^>]*aria-label="[^"]+")[^>]*>/i',
            $html,
        );
    }

    public function test_agent_screen_lead_fields_use_bound_label_and_control_ids(): void
    {
        $this->enableAgentScreenAccess();

        $html = $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertOk()
            ->getContent();

        $this->assertBoundLabelForControl($html, 'Phone Number', 'x-model="phoneNumber"');
        $this->assertBoundLabelForControl($html, 'Lead ID', 'x-model="leadId"');
    }

    public function test_agent_screen_capture_fields_use_bound_label_and_control_ids(): void
    {
        $this->enableAgentScreenAccess();
        AgentScreenField::query()->create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_name',
            'field_label' => 'Customer Name',
            'field_type' => 'text',
            'direction' => 'none',
            'field_order' => 1,
            'field_width' => 'half',
        ]);

        $html = $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertOk()
            ->getContent();

        $this->assertBoundLabelForControl($html, 'Customer Name', 'name="customer_name"');
    }

    public function test_agent_screen_releases_wrapup_on_vicidial_disposition_event(): void
    {
        $this->enableAgentScreenAccess();

        $html = $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            "/p\.event === 'dispo_set'.*?shouldReleaseWrapupForVicidialDisposition.*?this\.resetAfterDisposition\(\)/s",
            $html,
        );
    }

    private function enableAgentScreenAccess(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);
    }

    private function assertBoundLabelForControl(string $html, string $label, string $controlAttribute): void
    {
        $controlPattern = '/<(?:input|select|textarea)(?=[^>]*'.preg_quote($controlAttribute, '/').')(?=[^>]*id="([^"]+)")[^>]*>/is';
        $matched = preg_match($controlPattern, $html, $matches);

        $this->assertSame(1, $matched, "Expected the {$label} control to expose an id.");

        $id = $matches[1];
        $labelPattern = '/<label(?=[^>]*for="'.preg_quote($id, '/').'")[^>]*>.*?'.preg_quote($label, '/').'.*?<\/label>/is';
        $this->assertMatchesRegularExpression($labelPattern, $html, "Expected the {$label} label to reference #{$id}.");
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
