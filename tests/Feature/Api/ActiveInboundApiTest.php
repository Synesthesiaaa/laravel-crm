<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Telephony\LeadHydrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActiveInboundApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_active_inbound_returns_false_when_user_missing_vici_user(): void
    {
        $user = User::factory()->create(['role' => 'Agent', 'vici_user' => null]);

        $service = Mockery::mock(LeadHydrationService::class);
        $service->shouldNotReceive('probeInbound');
        $this->instance(LeadHydrationService::class, $service);

        $this->actingAs($user)
            ->getJson('/api/leads/active-inbound')
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    public function test_active_inbound_returns_false_when_probe_returns_null(): void
    {
        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'agent001',
            'default_campaign' => 'mbsales',
        ]);

        $service = Mockery::mock(LeadHydrationService::class);
        $service->shouldReceive('probeInbound')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'mbsales'
            )
            ->andReturn(null);
        $this->instance(LeadHydrationService::class, $service);

        $this->actingAs($user)
            ->getJson('/api/leads/active-inbound')
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    public function test_active_inbound_returns_payload_when_probe_succeeds(): void
    {
        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'agent002',
            'default_campaign' => 'mbsales',
        ]);

        $service = Mockery::mock(LeadHydrationService::class);
        $service->shouldReceive('probeInbound')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'mbsales'
            )
            ->andReturn([
                'lead_id' => '456',
                'phone_number' => '15551234567',
                'client_name' => 'Jane Doe',
                'capture_data' => ['customer_email' => 'jane@example.test'],
                'raw_fields' => ['email' => 'jane@example.test'],
            ]);
        $this->instance(LeadHydrationService::class, $service);

        $this->actingAs($user)
            ->getJson('/api/leads/active-inbound')
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('campaign', 'mbsales')
            ->assertJsonPath('lead_id', '456')
            ->assertJsonPath('phone_number', '15551234567')
            ->assertJsonPath('capture_data.customer_email', 'jane@example.test');
    }
}
