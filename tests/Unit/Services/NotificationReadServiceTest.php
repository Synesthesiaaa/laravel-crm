<?php

namespace Tests\Unit\Services;

use App\Models\NotificationReadState;
use App\Models\User;
use App\Services\Notifications\NotificationReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_derived_read_state_is_idempotent_and_prunable(): void
    {
        $user = User::factory()->create(['full_name' => 'Agent One']);
        $service = app(NotificationReadService::class);

        $this->assertTrue($service->markRead($user, 'daily:mbsales:'.now()->toDateString()));
        $this->assertTrue($service->markRead($user, 'daily:mbsales:'.now()->toDateString()));
        $this->assertSame(1, NotificationReadState::query()->count());

        NotificationReadState::query()->update(['read_at' => now()->subDays(91)]);
        $this->assertSame(1, $service->prune(90));
        $this->assertDatabaseCount('notification_read_states', 0);
    }

    public function test_user_cannot_mark_another_users_attendance_key_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->assertFalse(app(NotificationReadService::class)->markRead($other, 'attendance:99999'));
        $this->assertDatabaseCount('notification_read_states', 0);
        $this->assertTrue(app(NotificationReadService::class)->canRead($owner, 'daily:mbsales:'.now()->toDateString()));
    }
}
