<?php

namespace Tests\Feature\Admin\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadListsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'username' => 'admin',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->campaign = Campaign::create([
            'code' => 'testcamp',
            'name' => 'Test Campaign',
            'is_active' => true,
        ]);
    }

    public function test_agent_cannot_view_lead_lists(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $this->actingAs($agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->get(route('admin.leads.lists.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_lists_index(): void
    {
        LeadList::create([
            'campaign_code' => 'testcamp',
            'name' => 'Hot Leads',
            'active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->get(route('admin.leads.lists.index', ['campaign' => 'testcamp']));

        $response->assertOk();
        $response->assertSee('Hot Leads');
    }

    public function test_admin_can_create_list(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->post(route('admin.leads.lists.store'), [
                'campaign_code' => 'testcamp',
                'name' => 'New List',
                'description' => 'desc',
                'active' => true,
                'display_order' => 5,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lead_lists', ['name' => 'New List', 'campaign_code' => 'testcamp']);
    }

    public function test_disabling_list_purges_pending_hopper_rows(): void
    {
        $list = LeadList::create([
            'campaign_code' => 'testcamp',
            'name' => 'Queue A',
            'active' => true,
        ]);
        LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $list->id,
            'lead_id' => 'L1',
            'phone_number' => '5551111',
            'status' => 'pending',
        ]);
        LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $list->id,
            'lead_id' => 'L2',
            'phone_number' => '5552222',
            'status' => 'assigned',
            'assigned_to_user_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->post(route('admin.leads.lists.toggle', $list), ['active' => 0])
            ->assertRedirect();

        $this->assertSame(0, LeadHopper::where('list_id', $list->id)->where('status', 'pending')->count());
        // Assigned row should remain untouched
        $this->assertDatabaseHas('lead_hopper', ['lead_id' => 'L2', 'status' => 'assigned']);
        $this->assertDatabaseHas('lead_lists', ['id' => $list->id, 'active' => 0]);
    }

    public function test_deleting_list_removes_leads_and_purges_hopper(): void
    {
        $list = LeadList::create([
            'campaign_code' => 'testcamp',
            'name' => 'Dispose Me',
            'active' => true,
        ]);
        Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '555',
        ]);
        LeadHopper::create([
            'campaign_code' => 'testcamp',
            'list_id' => $list->id,
            'lead_id' => 'X',
            'phone_number' => '555',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->post(route('admin.leads.lists.destroy'), ['id' => $list->id])
            ->assertRedirect();

        $this->assertSoftDeleted('lead_lists', ['id' => $list->id]);
        $this->assertSame(0, LeadHopper::where('list_id', $list->id)->where('status', 'pending')->count());
    }
}
