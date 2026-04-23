<?php

namespace Tests\Feature\Api;

use App\Models\AgentScreenField;
use App\Models\CallSession;
use App\Models\DispositionCode;
use App\Models\Lead;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveAgentRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_save_persists_agent_call_disposition(): void
    {
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'note',
            'field_label' => 'Note',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        $user = User::factory()->create(['username' => 'agent1']);
        $list = LeadList::create(['campaign_code' => 'mbsales', 'name' => 'L', 'active' => true]);
        $lead = Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'mbsales',
            'phone_number' => '5559999',
            'enabled' => true,
            'status' => 'NEW',
        ]);
        $session = CallSession::factory()
            ->for($user)
            ->completed()
            ->create([
                'campaign_code' => 'mbsales',
                'disposition_code' => null,
            ]);

        $response = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])->postJson(route('api.agent.record.save'), [
            'campaign_code' => 'mbsales',
            'call_session_id' => $session->id,
            'lead_pk' => $lead->id,
            'phone_number' => $lead->phone_number,
            'disposition_code' => 'SALE',
            'remarks' => 'ok',
            'capture_data' => ['note' => 'hello'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('agent_call_dispositions', [
            'call_session_id' => $session->id,
            'disposition_code' => 'SALE',
            'lead_pk' => $lead->id,
        ]);
    }
}
