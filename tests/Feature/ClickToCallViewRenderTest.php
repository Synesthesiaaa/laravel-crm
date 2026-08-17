<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickToCallViewRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
    }

    public function test_authenticated_layout_does_not_render_merge_conflict_markers_in_click_to_call(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('records.index'));

        $response->assertOk();
        $response->assertDontSee('<<<<<<<', false);
        $response->assertDontSee('>>>>>>>', false);
        $response->assertSee('Quick Dial', false);
    }

    public function test_agent_screen_renders_global_disposition_modal_instead_of_inline_panel(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('agent.index'));

        $response->assertOk();
        $response->assertSee('Call Wrap-up', false);
        $response->assertSee('Select a disposition code before taking the next call.', false);
        $response->assertSee('Save & Ready', false);
        $response->assertDontSee('Save Disposition', false);
        $response->assertDontSee('Select a code and retry, or click Dismiss to return to idle.', false);
    }
}
