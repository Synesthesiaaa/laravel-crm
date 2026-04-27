<?php

namespace Tests\Feature\Api;

use App\Models\AgentCallDisposition;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSubmittedRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_sees_only_own_submitted_agent_source_records(): void
    {
        Campaign::create(['code' => 'mbsales', 'name' => 'MB', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'mbsales', 'name' => 'L', 'active' => true]);
        $lead = Lead::withoutEvents(fn () => Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'mbsales',
            'phone_number' => '5551111',
            'enabled' => true,
            'status' => 'SALE',
        ]));

        $agent = User::factory()->create();
        $other = User::factory()->create();

        AgentCallDisposition::create([
            'campaign_code' => 'mbsales',
            'lead_pk' => $lead->id,
            'phone_number' => '5551111',
            'user_id' => $agent->id,
            'agent' => 'Me',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
            'disposition_source' => AgentCallDisposition::SOURCE_AGENT,
            'capture_data' => ['note' => 'hello'],
            'called_at' => now(),
        ]);

        AgentCallDisposition::create([
            'campaign_code' => 'mbsales',
            'lead_pk' => $lead->id,
            'phone_number' => '5552222',
            'user_id' => $other->id,
            'agent' => 'Other',
            'disposition_code' => 'DNC',
            'disposition_label' => 'DNC',
            'disposition_source' => AgentCallDisposition::SOURCE_AGENT,
            'called_at' => now(),
        ]);

        AgentCallDisposition::create([
            'campaign_code' => 'mbsales',
            'lead_pk' => $lead->id,
            'phone_number' => '5553333',
            'user_id' => null,
            'agent' => 'VICIDIAL_AUTO',
            'disposition_code' => 'NO_ANSWER',
            'disposition_label' => 'NA',
            'disposition_source' => AgentCallDisposition::SOURCE_VICIDIAL_WEBHOOK,
            'called_at' => now(),
        ]);

        $res = $this->actingAs($agent)->withSession(['campaign' => 'mbsales'])->getJson(route('api.agent.submitted-records'));

        $res->assertOk();
        $res->assertJsonPath('meta.total', 1);
        $res->assertJsonPath('data.0.disposition_code', 'SALE');
        $res->assertJsonPath('data.0.lead_current_status', 'SALE');
    }

    public function test_export_csv_streams_rows(): void
    {
        Campaign::create(['code' => 'mbsales', 'name' => 'MB', 'is_active' => true]);
        $agent = User::factory()->create();

        AgentCallDisposition::create([
            'campaign_code' => 'mbsales',
            'phone_number' => '5550001',
            'user_id' => $agent->id,
            'agent' => 'Me',
            'disposition_code' => 'NEW',
            'disposition_label' => 'New',
            'disposition_source' => AgentCallDisposition::SOURCE_AGENT,
            'called_at' => now(),
        ]);

        $response = $this->actingAs($agent)->withSession(['campaign' => 'mbsales'])
            ->get(route('api.agent.submitted-records.export'));

        $response->assertOk();
        $this->assertStringContainsString('disposition_code', $response->streamedContent());
        $this->assertStringContainsString('5550001', $response->streamedContent());
    }
}
