<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AgentNextLeadPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_lead_includes_fields_map(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'A', 'active' => true]);
        $lead = Lead::withoutEvents(fn () => Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5551234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'enabled' => true,
            'status' => 'NEW',
            'custom_fields' => ['my_extra' => 'X'],
        ]));
        LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $list->id,
            'lead_pk' => $lead->id,
            'lead_id' => (string) $lead->id,
            'phone_number' => $lead->phone_number,
            'client_name' => 'Jane Doe',
            'status' => 'pending',
            'custom_data' => ['city' => 'Manila'],
        ]);
        Cache::flush();

        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('api.leads.next', ['campaign' => 'testcamp']));

        $response->assertOk();
        $response->assertJsonPath('lead.lead_pk', $lead->id);
        $response->assertJsonPath('lead.fields.first_name', 'Jane');
        $response->assertJsonPath('lead.fields.email', 'jane@example.com');
        $response->assertJsonPath('lead.fields.my_extra', 'X');
        $response->assertJsonPath('lead.fields.city', 'Manila');
    }
}
