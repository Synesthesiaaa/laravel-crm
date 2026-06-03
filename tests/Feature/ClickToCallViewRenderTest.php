<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickToCallViewRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_agent_screen_uses_inline_disposition_not_global_modal(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('agent.index'));

        $response->assertOk();
        $response->assertSee('Save Disposition', false);
        $response->assertDontSee('Call Wrap-up — Disposition Required', false);
    }
}
