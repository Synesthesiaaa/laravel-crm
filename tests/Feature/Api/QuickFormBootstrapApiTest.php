<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class QuickFormBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bootstrap_requires_authentication(): void
    {
        $this->getJson('/api/forms/quick/bootstrap')->assertUnauthorized();
    }

    public function test_bootstrap_returns_active_campaign_first_form(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(CampaignService::class);
        $service->shouldReceive('getCampaign')
            ->once()
            ->with('mbsales')
            ->andReturn([
                'name' => 'MB Sales',
                'forms' => [
                    'verification' => ['name' => 'Verification'],
                    'disposition' => ['name' => 'Disposition'],
                ],
            ]);
        $this->instance(CampaignService::class, $service);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson('/api/forms/quick/bootstrap')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('campaign', 'mbsales')
            ->assertJsonPath('form_type', 'verification')
            ->assertJsonPath('form_name', 'Verification');
    }
}
