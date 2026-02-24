<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(UserService::class);
    }

    public function test_create_creates_user_with_hashed_password(): void
    {
        $user = $this->service->create([
            'username'  => 'newuser',
            'full_name' => 'New User',
            'password'  => 'Password1',
            'role'      => 'Agent',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('newuser', $user->username);
        $this->assertDatabaseHas('users', ['username' => 'newuser']);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Password1', $user->password));
    }

    public function test_delete_prevents_self_deletion(): void
    {
        $user = User::factory()->create(['role' => 'Super Admin']);
        $result = $this->service->delete($user, $user);
        $this->assertFalse($result);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_removes_other_user(): void
    {
        $admin  = User::factory()->create(['role' => 'Super Admin']);
        $target = User::factory()->create(['role' => 'Agent']);

        $result = $this->service->delete($target, $admin);
        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }
}
