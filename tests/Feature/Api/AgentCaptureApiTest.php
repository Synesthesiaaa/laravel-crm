<?php

namespace Tests\Feature\Api;

use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use App\Models\CallSession;
use App\Models\User;
use App\Services\Telephony\LeadService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentCaptureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_capture_store_pushes_only_post_and_both_writeable_vicidial_fields(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'email_capture',
            'vici_field' => 'email',
            'direction' => 'post',
            'field_label' => 'Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'comments_capture',
            'vici_field' => 'comments',
            'direction' => 'both',
            'field_label' => 'Comments',
            'field_order' => 2,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'first_name_capture',
            'vici_field' => 'first_name',
            'direction' => 'get',
            'field_label' => 'First Name',
            'field_order' => 3,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'status_capture',
            'vici_field' => 'status',
            'direction' => 'post',
            'field_label' => 'Status',
            'field_order' => 4,
            'field_width' => 'full',
        ]);

        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('updateFields')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'mbsales',
                Mockery::on(function ($payload) {
                    if (! is_array($payload)) {
                        return false;
                    }

                    return ($payload['lead_id'] ?? null) === '123'
                        && ($payload['email'] ?? null) === 'agent@example.test'
                        && ($payload['comments'] ?? null) === 'Follow up tomorrow'
                        && ! array_key_exists('first_name', $payload)
                        && ! array_key_exists('status', $payload);
                }),
            )
            ->andReturn(OperationResult::success(['raw_response' => 'SUCCESS']));
        $this->instance(LeadService::class, $leadService);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/agent/capture', [
                'campaign_code' => 'mbsales',
                'lead_id' => '123',
                'phone_number' => '15551234567',
                'capture_data' => [
                    'email_capture' => 'agent@example.test',
                    'comments_capture' => 'Follow up tomorrow',
                    'first_name_capture' => 'Ignored (GET only)',
                    'status_capture' => 'Ignored (readonly)',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('agent_capture_records', [
            'campaign_code' => 'mbsales',
            'lead_id' => '123',
            'phone_number' => '15551234567',
        ]);
    }

    public function test_capture_store_normalizes_percentage_fields_for_storage_and_vicidial_push(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'discount_rate',
            'vici_field' => 'comments',
            'direction' => 'post',
            'field_label' => 'Discount Rate',
            'field_type' => 'percentage',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('updateFields')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => (int) $authUser->id === (int) $user->id),
                'mbsales',
                Mockery::on(fn ($payload) => is_array($payload)
                    && ($payload['lead_id'] ?? null) === '123'
                    && ($payload['comments'] ?? null) === '12.5%'),
            )
            ->andReturn(OperationResult::success(['raw_response' => 'SUCCESS']));
        $this->instance(LeadService::class, $leadService);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/agent/capture', [
                'campaign_code' => 'mbsales',
                'lead_id' => '123',
                'capture_data' => [
                    'discount_rate' => '12.5',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $record = AgentCaptureRecord::query()->where('campaign_code', 'mbsales')->firstOrFail();
        $this->assertSame(['discount_rate' => '12.5%'], $record->capture_data);
    }

    public function test_capture_rejects_call_session_owned_by_another_agent(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $otherUser = User::factory()->create(['role' => 'Agent']);
        $session = CallSession::factory()->create([
            'user_id' => $otherUser->id,
            'campaign_code' => 'mbsales',
        ]);

        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'email_capture',
            'field_label' => 'Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/agent/capture', [
                'campaign_code' => 'mbsales',
                'call_session_id' => $session->id,
                'capture_data' => [
                    'email_capture' => 'agent@example.test',
                ],
                'visible_fields' => ['email_capture'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['call_session_id']);
    }

    public function test_capture_requires_visible_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'email_capture',
            'field_label' => 'Email',
            'is_required' => true,
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/agent/capture', [
                'campaign_code' => 'mbsales',
                'capture_data' => [
                    'email_capture' => '',
                ],
                'visible_fields' => ['email_capture'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capture_data.email_capture']);
    }

    public function test_capture_does_not_require_hidden_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'email_capture',
            'field_label' => 'Email',
            'is_required' => true,
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/agent/capture', [
                'campaign_code' => 'mbsales',
                'capture_data' => [
                    'email_capture' => '',
                ],
                'visible_fields' => [],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
