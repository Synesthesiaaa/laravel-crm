<?php

namespace Tests\Feature\Admin\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private LeadList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'username' => 'admin',
            'role' => User::ROLE_ADMIN,
        ]);
        Campaign::create(['code' => 'testcamp', 'name' => 'Test', 'is_active' => true]);
        $this->list = LeadList::create([
            'campaign_code' => 'testcamp',
            'name' => 'Alpha',
            'active' => true,
        ]);
    }

    public function test_admin_can_create_lead(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test'])
            ->post(route('admin.leads.leads.store', $this->list), [
                'list_id' => $this->list->id,
                'phone_number' => '5550001',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'status' => 'NEW',
                'enabled' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'list_id' => $this->list->id,
            'phone_number' => '5550001',
            'first_name' => 'Jane',
        ]);
    }

    public function test_admin_can_update_lead(): void
    {
        $lead = Lead::create([
            'list_id' => $this->list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5550001',
            'first_name' => 'A',
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test'])
            ->put(route('admin.leads.leads.update', ['list' => $this->list, 'lead' => $lead]), [
                'phone_number' => '5550001',
                'first_name' => 'B',
                'status' => 'NEW',
                'enabled' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'first_name' => 'B']);
    }

    public function test_admin_can_bulk_disable_leads(): void
    {
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $ids[] = Lead::create([
                'list_id' => $this->list->id,
                'campaign_code' => 'testcamp',
                'phone_number' => '555000'.$i,
                'enabled' => true,
            ])->id;
        }

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test'])
            ->post(route('admin.leads.leads.bulk', $this->list), [
                'ids' => $ids,
                'action' => 'disable',
            ])
            ->assertRedirect();

        $this->assertSame(3, Lead::whereIn('id', $ids)->where('enabled', false)->count());
    }

    public function test_admin_can_soft_delete_lead(): void
    {
        $lead = Lead::create([
            'list_id' => $this->list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5550001',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test'])
            ->post(route('admin.leads.leads.destroy', $this->list), ['id' => $lead->id])
            ->assertRedirect();

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }
}
