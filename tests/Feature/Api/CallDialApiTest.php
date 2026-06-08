<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Models\VicidialServer;
use App\Services\Telephony\CallOrchestrationService;
use App\Services\Telephony\LeadHydrationService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
                '15551234567',
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

    public function test_dial_prefers_selected_vicidial_campaign_over_stale_query_campaign(): void
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
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'VICI_B',
                '15551234567',
                123,
                '1',
            )
            ->andReturn(OperationResult::success(['session_id' => 999]));
        $this->instance(CallOrchestrationService::class, $orchestration);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'VICI_B',
                123,
                '15551234567',
            )
            ->andReturn([
                'lead_id' => '123',
                'phone_number' => '15551234567',
                'client_name' => null,
                'capture_data' => [],
                'raw_fields' => [],
            ]);
        $this->instance(LeadHydrationService::class, $hydration);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB',
                'vicidial_campaign' => 'VICI_B',
                'vicidial_campaign_name' => 'VICIdial B',
            ])
            ->postJson('/api/call/dial?campaign=mbsales', [
                'phone_number' => '15551234567',
                'lead_id' => 123,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('session_id', 999);
    }

    public function test_dial_requires_usable_session_for_selected_vicidial_campaign_not_crm_campaign(): void
    {
        config(['vicidial.require_vicidial_agent_session_before_dial' => true]);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'secret',
            'extension' => '6001',
        ]);

        VicidialAgentSession::factory()->create([
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'session_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB',
                'vicidial_campaign' => 'VICI_B',
                'vicidial_campaign_name' => 'VICIdial B',
            ])
            ->postJson('/api/call/dial?campaign=mbsales', ['phone_number' => '15551234567'])
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'VICIDIAL_AGENT_NOT_LOGGED_IN');
    }

    public function test_manual_dial_allows_selected_vicidial_campaign_without_crm_campaign_row(): void
    {
        config(['vicidial.require_vicidial_agent_session_before_dial' => true]);
        Queue::fake();
        Http::fake(['*' => Http::response('SUCCESS: external_dial sent', 200)]);

        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'secret',
            'extension' => '6001',
        ]);

        VicidialServer::factory()->create([
            'campaign_code' => 'VICI_B',
            'api_url' => 'https://vici.example.com/agc/api.php',
            'is_active' => true,
            'is_default' => true,
        ]);
        VicidialAgentSession::factory()->create([
            'user_id' => $user->id,
            'campaign_code' => 'VICI_B',
            'session_status' => 'ready',
        ]);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'VICI_B',
                null,
                '15551234567',
            )
            ->andReturn([
                'lead_id' => null,
                'phone_number' => '15551234567',
                'client_name' => null,
                'capture_data' => [],
                'raw_fields' => [],
            ]);
        $this->instance(LeadHydrationService::class, $hydration);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB',
                'vicidial_campaign' => 'VICI_B',
                'vicidial_campaign_name' => 'VICIdial B',
            ])
            ->postJson('/api/call/dial?campaign=mbsales', ['phone_number' => '15551234567'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone_number', '15551234567');

        $this->assertDatabaseHas('call_sessions', [
            'user_id' => $user->id,
            'campaign_code' => 'VICI_B',
            'phone_number' => '15551234567',
        ]);
    }

    public function test_predictive_dial_uses_selected_vicidial_campaign_and_reports_disabled_when_not_configured(): void
    {
        $user = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'secret',
            'extension' => '6001',
        ]);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'mbsales',
                'campaign_name' => 'MB',
                'vicidial_campaign' => 'VICI_B',
                'vicidial_campaign_name' => 'VICIdial B',
            ])
            ->postJson('/api/call/predictive-dial?campaign=mbsales')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Predictive dialing is disabled for this campaign.');
    }
}
