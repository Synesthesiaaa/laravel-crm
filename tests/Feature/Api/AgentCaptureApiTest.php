<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessTelephonySyncOutboxJob;
use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use App\Models\CallSession;
use App\Models\SystemSetting;
use App\Models\TelephonySyncOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgentCaptureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);
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

        Queue::fake();

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
        $outbox = TelephonySyncOutbox::query()->where('operation_type', 'vicidial_lead_fields')->firstOrFail();
        $payload = json_decode(Crypt::decryptString($outbox->payload_encrypted), true);
        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame('mbsales', $payload['campaign']);
        $this->assertSame('123', $payload['fields']['lead_id']);
        $this->assertSame('agent@example.test', $payload['fields']['email']);
        $this->assertSame('Follow up tomorrow', $payload['fields']['comments']);
        $this->assertArrayNotHasKey('first_name', $payload['fields']);
        $this->assertArrayNotHasKey('status', $payload['fields']);
        $this->assertStringNotContainsString('agent@example.test', $outbox->payload_encrypted);
        Queue::assertPushed(ProcessTelephonySyncOutboxJob::class, fn (ProcessTelephonySyncOutboxJob $job): bool => $job->outboxId === $outbox->id);
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

        Queue::fake();

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
        $outbox = TelephonySyncOutbox::query()->where('operation_type', 'vicidial_lead_fields')->firstOrFail();
        $payload = json_decode(Crypt::decryptString($outbox->payload_encrypted), true);
        $this->assertSame('12.5%', $payload['fields']['comments']);
        Queue::assertPushed(ProcessTelephonySyncOutboxJob::class, fn (ProcessTelephonySyncOutboxJob $job): bool => $job->outboxId === $outbox->id);
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
            'field_key' => 'show_email',
            'field_label' => 'Show email field',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'email_capture',
            'field_label' => 'Email',
            'is_required' => true,
            'visibility' => ['field' => 'show_email', 'operator' => 'equals', 'values' => ['Yes']],
            'field_order' => 2,
            'field_width' => 'full',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson('/api/agent/capture', [
                'campaign_code' => 'mbsales',
                'capture_data' => [
                    'show_email' => 'No',
                    'email_capture' => 'forged-hidden-value@example.test',
                ],
                // The server must ignore this client claim and recalculate visibility.
                'visible_fields' => ['email_capture'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $record = AgentCaptureRecord::query()->firstOrFail();
        $this->assertSame(['show_email' => 'No'], $record->capture_data);
    }
}
