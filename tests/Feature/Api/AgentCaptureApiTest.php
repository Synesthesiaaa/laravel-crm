<?php

namespace Tests\Feature\Api;

use App\Models\AgentScreenField;
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
                })
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
}
