<?php

namespace Tests\Feature;

use App\Jobs\PushLeadToHopperJob;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class LeadObserverHopperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Campaign::create(['code' => 'testcamp', 'name' => 'Test', 'is_active' => true]);
    }

    public function test_creating_new_lead_dispatches_push_to_hopper_job(): void
    {
        Bus::fake([PushLeadToHopperJob::class]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);

        Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5551111',
            'enabled' => true,
            'status' => 'NEW',
        ]);

        Bus::assertDispatched(PushLeadToHopperJob::class);
    }

    public function test_updating_lead_to_non_hopper_status_purges_pending_hopper_rows(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
        $lead = Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5552222',
            'enabled' => true,
            'status' => 'NEW',
        ]);
        $hop = LeadHopper::where('lead_pk', $lead->id)->where('status', 'pending')->first();
        $this->assertNotNull($hop);

        $lead->update(['status' => 'SALE']);

        $this->assertDatabaseMissing('lead_hopper', ['id' => $hop->id]);
    }
}
