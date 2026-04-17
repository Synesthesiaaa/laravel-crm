<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\DispositionCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositionCodesControllerTest extends TestCase
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

    public function test_agent_cannot_access_disposition_codes(): void
    {
        $agent = User::factory()->create(['role' => 'Agent']);
        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->get(route('admin.disposition-codes.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_create_disposition_code(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->post(route('admin.disposition-codes.store'), [
                'campaign_code' => 'testcamp',
                'code' => 'SALE',
                'label' => 'Sale',
                'sort_order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('disposition_codes', [
            'campaign_code' => 'testcamp',
            'code' => 'SALE',
        ]);
    }

    public function test_admin_can_delete_disposition_code(): void
    {
        $code = DispositionCode::create([
            'campaign_code' => 'testcamp',
            'code' => 'DNC',
            'label' => 'Do Not Call',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Campaign'])
            ->post(route('admin.disposition-codes.destroy'), ['id' => $code->id]);

        $response->assertRedirect();
        $this->assertSoftDeleted('disposition_codes', ['id' => $code->id]);
    }
}
