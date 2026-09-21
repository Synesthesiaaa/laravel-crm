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

    public function test_dial_returns_immediately_and_leaves_lead_hydration_to_the_agent_screen(): void
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
        $hydration->shouldNotReceive('hydrate');
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
            ->assertJsonPath('lead_id', 123)
            ->assertJsonPath('phone_number', '15551234567')
            ->assertJsonPath('client_name', null)
            ->assertJsonPath('lead_data', []);
    }
}
