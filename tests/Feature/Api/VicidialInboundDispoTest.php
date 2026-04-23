<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VicidialInboundDispoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vicidial.inbound_dispo_enabled' => true]);
        Campaign::create(['code' => 'testcamp', 'name' => 'Test', 'is_active' => true]);
    }

    public function test_dispo_set_webhook_updates_lead_status_and_completes_hopper(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
        $lead = Lead::withoutEvents(fn () => Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5550001',
            'enabled' => true,
            'status' => 'NEW',
        ]));
        $hop = LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $list->id,
            'lead_pk' => $lead->id,
            'lead_id' => (string) $lead->id,
            'phone_number' => $lead->phone_number,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/webhooks/vicidial-events', [
            'user' => 'nobody',
            'event' => 'dispo_set',
            'message' => 'NA',
            'lead_id' => (string) $lead->id,
        ]);

        $response->assertOk();
        $lead->refresh();
        $this->assertSame('NO_ANSWER', $lead->status);
        $this->assertSame('completed', $hop->fresh()->status);
        $this->assertDatabaseHas('agent_call_dispositions', [
            'lead_pk' => $lead->id,
            'disposition_code' => 'NO_ANSWER',
            'disposition_source' => 'vicidial_webhook',
        ]);
    }
}
