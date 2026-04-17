<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AuthService::class);
    }

    public function test_attempt_returns_null_for_invalid_credentials(): void
    {
        $result = $this->service->attempt('nonexistent', 'wrongpass');
        $this->assertNull($result);
    }

    public function test_attempt_returns_user_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'role' => 'Agent',
        ]);

        $result = $this->service->attempt('testuser', 'password123');
        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_log_attendance_creates_record(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        Event::fake();

        $this->service->logAttendance($user->id, 'login', '127.0.0.1');

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id,
            'event_type' => 'login',
            'ip_address' => '127.0.0.1',
        ]);
    }
}
