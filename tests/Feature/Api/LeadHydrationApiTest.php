<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Telephony\LeadHydrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeadHydrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hydrate_endpoint_returns_autofill_payload_for_lead_id(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        $service = Mockery::mock(LeadHydrationService::class);
        $service->shouldReceive('hydrate')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'mbsales',
                123,
                null
            )
            ->andReturn([
                'lead_id' => '123',
                'phone_number' => '15551234567',
                'client_name' => 'Jane Doe',
                'capture_data' => ['customer_email' => 'jane@example.test'],
                'raw_fields' => ['email' => 'jane@example.test'],
            ]);
        $this->instance(LeadHydrationService::class, $service);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->getJson('/api/leads/hydrate?campaign=mbsales&lead_id=123')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead_id', '123')
            ->assertJsonPath('data.client_name', 'Jane Doe')
            ->assertJsonPath('data.capture_data.customer_email', 'jane@example.test');
    }

    public function test_hydrate_endpoint_requires_lead_id_or_phone_number(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->getJson('/api/leads/hydrate?campaign=mbsales')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
