<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create([
            'username' => 'admin',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->get(route('admin.users.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_index_requires_super_admin_role(): void
    {
        $agent = User::factory()->create(['role' => 'Agent']);
        $response = $this->actingAs($agent)->get(route('admin.users.index'));
        $response->assertForbidden();
    }

    public function test_super_admin_can_view_users_list(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'test', 'campaign_name' => 'Test'])
            ->get(route('admin.users.index'));
        $response->assertOk();
    }

    public function test_store_creates_user(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'test', 'campaign_name' => 'Test'])
            ->post(route('admin.users.store'), [
                'username' => 'newagent',
                'full_name' => 'New Agent',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'Agent',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['username' => 'newagent']);
    }

    public function test_store_validates_unique_username(): void
    {
        User::factory()->create(['username' => 'existinguser', 'role' => 'Agent']);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'test', 'campaign_name' => 'Test'])
            ->post(route('admin.users.store'), [
                'username' => 'existinguser',
                'full_name' => 'Duplicate',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'Agent',
            ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_destroy_deletes_other_user(): void
    {
        $target = User::factory()->create(['role' => 'Agent']);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'test', 'campaign_name' => 'Test'])
            ->post(route('admin.users.destroy'), ['id' => $target->id]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_destroy_prevents_self_deletion(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'test', 'campaign_name' => 'Test'])
            ->post(route('admin.users.destroy'), ['id' => $this->superAdmin->id]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }
}
