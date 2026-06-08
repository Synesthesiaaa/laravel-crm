<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_does_not_render_sample_agents(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.supervisor'))
            ->assertOk()
            ->assertSee('Live supervisor data unavailable')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('Juan Cruz');
    }
}
