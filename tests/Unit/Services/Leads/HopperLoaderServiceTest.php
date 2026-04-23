<?php

namespace Tests\Unit\Services\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use App\Services\Leads\HopperLoaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HopperLoaderServiceTest extends TestCase
{
    use RefreshDatabase;

    private HopperLoaderService $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = app(HopperLoaderService::class);
        Campaign::create(['code' => 'testcamp', 'name' => 'Test', 'is_active' => true]);
    }

    public function test_load_list_does_not_push_sale_status_leads(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'A', 'active' => true]);
        Lead::create(['list_id' => $list->id, 'campaign_code' => 'testcamp', 'phone_number' => '999', 'enabled' => true, 'status' => 'SALE']);

        $this->assertSame(0, $this->loader->loadList($list));
        $this->assertSame(0, LeadHopper::count());
    }

    public function test_load_list_pushes_enabled_dialable_leads(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'A', 'active' => true]);

        Lead::withoutEvents(function () use ($list) {
            Lead::create(['list_id' => $list->id, 'campaign_code' => 'testcamp', 'phone_number' => '111', 'enabled' => true, 'status' => 'NEW']);
            Lead::create(['list_id' => $list->id, 'campaign_code' => 'testcamp', 'phone_number' => '222', 'enabled' => true, 'status' => 'DNC']);
            Lead::create(['list_id' => $list->id, 'campaign_code' => 'testcamp', 'phone_number' => '333', 'enabled' => false, 'status' => 'NEW']);
        });

        $count = $this->loader->loadList($list);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('lead_hopper', ['list_id' => $list->id, 'phone_number' => '111']);
        $this->assertDatabaseMissing('lead_hopper', ['phone_number' => '222']);
        $this->assertDatabaseMissing('lead_hopper', ['phone_number' => '333']);
    }

    public function test_load_list_skips_disabled_list(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'Disabled', 'active' => false]);
        Lead::withoutEvents(fn () => Lead::create(['list_id' => $list->id, 'campaign_code' => 'testcamp', 'phone_number' => '111', 'enabled' => true, 'status' => 'NEW']));

        $this->assertSame(0, $this->loader->loadList($list));
        $this->assertSame(0, LeadHopper::count());
    }

    public function test_load_list_does_not_duplicate_existing_pending_rows(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'A', 'active' => true]);
        $lead = Lead::withoutEvents(fn () => Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '111',
            'enabled' => true,
            'status' => 'NEW',
        ]));
        LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $list->id,
            'lead_pk' => $lead->id,
            'lead_id' => (string) $lead->id,
            'phone_number' => $lead->phone_number,
            'status' => 'pending',
        ]);

        $this->assertSame(0, $this->loader->loadList($list));
        $this->assertSame(1, LeadHopper::count());
    }

    public function test_purge_pending_keeps_assigned_rows(): void
    {
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'A', 'active' => true]);
        LeadHopper::create(['campaign_code' => 'testcamp', 'list_id' => $list->id, 'lead_id' => '1', 'phone_number' => '1', 'status' => 'pending']);
        LeadHopper::create(['campaign_code' => 'testcamp', 'list_id' => $list->id, 'lead_id' => '2', 'phone_number' => '2', 'status' => 'assigned']);

        $this->assertSame(1, $this->loader->purgePendingForList($list->id));
        $this->assertSame(1, LeadHopper::where('list_id', $list->id)->count());
    }
}
