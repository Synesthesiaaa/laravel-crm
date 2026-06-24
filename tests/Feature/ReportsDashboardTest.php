<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_dashboard_sections_and_collapsed_debug_area(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM_LEADER,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB Sales',
            ])
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Operational Snapshot');
        $response->assertSee('Call Status Dashboard');
        $response->assertSee('Agent Performance');
        $response->assertSee('Disposition Breakdown');
        $response->assertSee('Debug / Raw VICIdial Output');
        $response->assertSee('<details', false);
        $response->assertDontSee('role="tablist"', false);
        $response->assertDontSee('Recording Browser');
    }

    public function test_reports_page_requires_higher_role_access(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_AGENT,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB Sales',
            ])
            ->get(route('reports.index'));

        $response->assertForbidden();
    }
}
