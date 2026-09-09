<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardActivityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_activity_endpoint_requires_authentication(): void
    {
        $this->getJson(route('api.dashboard.activity'))
            ->assertUnauthorized();
    }

    public function test_activity_endpoint_returns_all_lazy_dashboard_trend_shapes(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->getJson(route('api.dashboard.activity'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'activity' => [
                    'daily' => ['labels', 'values'],
                    'weekly' => ['labels', 'values'],
                    'monthly' => ['labels', 'values'],
                ],
            ]);
    }
}
