<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Telephony\CallOrchestrationService;
use App\Services\Telephony\LeadHydrationService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CallDialApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dial_requires_vicidial_agent_session_when_enabled(): void
    {
        config(['vicidial.require_vicidial_agent_session_before_dial' => true]);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'secret',
            'extension' => '6001',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB'])
            ->postJson('/api/call/dial?campaign=mbsales', ['phone_number' => '15551234567'])
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'VICIDIAL_AGENT_NOT_LOGGED_IN');
    }

    public function test_dial_returns_hydrated_lead_data_when_available(): void
    {
        config(['vicidial.require_vicidial_agent_session_before_dial' => false]);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'secret',
            'extension' => '6001',
        ]);

        $orchestration = Mockery::mock(CallOrchestrationService::class);
        $orchestration->shouldReceive('startOutboundCall')
            ->once()
            ->andReturn(OperationResult::success(['session_id' => 999]));
        $this->instance(CallOrchestrationService::class, $orchestration);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'mbsales',
                123,
                '15551234567'
            )
            ->andReturn([
                'lead_id' => '123',
                'phone_number' => '15551234567',
                'client_name' => 'Jane Doe',
                'capture_data' => [
                    'customer_email' => 'jane@example.test',
                ],
                'raw_fields' => [],
            ]);
        $this->instance(LeadHydrationService::class, $hydration);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB'])
            ->postJson('/api/call/dial?campaign=mbsales', [
                'phone_number' => '15551234567',
                'lead_id' => 123,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('session_id', 999)
            ->assertJsonPath('client_name', 'Jane Doe')
            ->assertJsonPath('lead_data.customer_email', 'jane@example.test');
    }
}
