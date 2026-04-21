<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\LeadHopper;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NextLeadHopperFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        Cache::flush();
    }

    public function test_next_lead_skips_disabled_list_entries(): void
    {
        $active = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'Active', 'active' => true]);
        $disabled = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'Disabled', 'active' => false]);

        $disabledEntry = LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $disabled->id,
            'lead_id' => 'D1',
            'phone_number' => '5550001',
            'status' => 'pending',
        ]);
        $activeEntry = LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $active->id,
            'lead_id' => 'A1',
            'phone_number' => '5550002',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('api.leads.next', ['campaign' => 'testcamp']));

        $response->assertOk();
        $response->assertJsonPath('lead.lead_id', 'A1');

        $this->assertSame('pending', $disabledEntry->fresh()->status);
        $this->assertSame('assigned', $activeEntry->fresh()->status);
    }

    public function test_next_lead_allows_legacy_rows_with_null_list_id(): void
    {
        LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => null,
            'lead_id' => 'Legacy1',
            'phone_number' => '5550099',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('api.leads.next', ['campaign' => 'testcamp']));

        $response->assertOk();
        $response->assertJsonPath('lead.lead_id', 'Legacy1');
    }
}
