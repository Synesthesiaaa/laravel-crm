<?php

namespace Tests\Feature\Admin;

use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureRecordsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
    }

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    public function test_index_lists_records_for_session_campaign_with_dynamic_columns(): void
    {
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 101,
            'phone_number' => '639111111111',
            'agent' => 'agent_one',
            'user_id' => $this->admin->id,
            'capture_data' => ['customer_email' => 'one@example.com'],
        ]);

        AgentCaptureRecord::create([
            'campaign_code' => 'othercamp',
            'lead_id' => 999,
            'phone_number' => '639999999999',
            'agent' => 'agent_other',
            'capture_data' => ['customer_email' => 'hidden@example.com'],
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.capture-records.index'))
            ->assertOk()
            ->assertSee('Customer Email')
            ->assertSee('one@example.com')
            ->assertDontSee('hidden@example.com');
    }

    public function test_filters_by_agent_lead_phone_date(): void
    {
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'comments',
            'field_label' => 'Comments',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $match = AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 2222,
            'phone_number' => '639121234567',
            'agent' => 'agent_filter_ok',
            'user_id' => $this->admin->id,
            'capture_data' => ['comments' => 'matching record'],
        ]);
        $match->forceFill(['created_at' => '2026-05-18 09:00:00'])->save();

        $wrongAgent = AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 2222,
            'phone_number' => '639121234567',
            'agent' => 'agent_wrong',
            'capture_data' => ['comments' => 'wrong agent'],
        ]);
        $wrongAgent->forceFill(['created_at' => '2026-05-18 09:00:00'])->save();

        $wrongDate = AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 2222,
            'phone_number' => '639121234567',
            'agent' => 'agent_filter_ok',
            'capture_data' => ['comments' => 'wrong date'],
        ]);
        $wrongDate->forceFill(['created_at' => '2026-05-10 09:00:00'])->save();

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.capture-records.index', [
                'agent' => 'filter_ok',
                'lead_id' => '2222',
                'phone' => '1234567',
                'from_date' => '2026-05-17',
                'to_date' => '2026-05-19',
            ]))
            ->assertOk()
            ->assertSee('matching record')
            ->assertDontSee('wrong agent')
            ->assertDontSee('wrong date');
    }

    public function test_update_persists_capture_data_and_drops_unknown_keys(): void
    {
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $record = AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 101,
            'phone_number' => '639111111111',
            'agent' => 'agent_edit',
            'user_id' => $this->admin->id,
            'capture_data' => ['customer_email' => 'old@example.com'],
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.capture-records.update', ['record' => $record->id]), [
                'lead_id' => '202',
                'phone_number' => '639122222222',
                'capture_data' => [
                    'customer_email' => 'updated@example.com',
                    'unknown_field' => 'should_not_save',
                ],
            ])
            ->assertRedirect(route('admin.capture-records.index'));

        $record->refresh();
        $this->assertSame('202', (string) $record->lead_id);
        $this->assertSame('639122222222', (string) $record->phone_number);
        $this->assertSame(['customer_email' => 'updated@example.com'], $record->capture_data);
    }

    public function test_destroy_deletes_record(): void
    {
        $record = AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 303,
            'phone_number' => '639133333333',
            'agent' => 'agent_delete',
            'capture_data' => ['notes' => 'to be deleted'],
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.capture-records.destroy'), ['id' => $record->id])
            ->assertRedirect(route('admin.capture-records.index'));

        $this->assertDatabaseMissing('agent_capture_records', ['id' => $record->id]);
    }

    public function test_export_streams_csv_with_meta_and_capture_columns(): void
    {
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'consent',
            'field_label' => 'Consent',
            'field_type' => 'checkbox',
            'field_order' => 2,
            'field_width' => 'full',
        ]);

        AgentCaptureRecord::create([
            'campaign_code' => 'mbsales',
            'lead_id' => 707,
            'phone_number' => '639144444444',
            'agent' => 'agent_export',
            'capture_data' => [
                'customer_email' => 'export@example.com',
                'consent' => '1',
            ],
        ]);
        AgentCaptureRecord::create([
            'campaign_code' => 'othercamp',
            'lead_id' => 808,
            'phone_number' => '639155555555',
            'agent' => 'agent_other',
            'capture_data' => [
                'customer_email' => 'other@example.com',
                'consent' => '0',
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.capture-records.export'), [
                'from_date' => '2026-05-01',
                'to_date' => '2026-05-31',
            ]);

        $response->assertOk()->assertStreamed();

        $content = $response->streamedContent();
        $this->assertStringContainsString('id,created_at,agent,lead_id,phone_number,customer_email,consent', $content);
        $this->assertStringContainsString('agent_export', $content);
        $this->assertStringContainsString('export@example.com', $content);
        $this->assertStringNotContainsString('other@example.com', $content);
    }
}
