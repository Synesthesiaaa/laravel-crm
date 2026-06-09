<?php

namespace Tests\Feature\Api;

use App\Models\CrmCallHistory;
use App\Models\User;
use App\Notifications\SupervisorUserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_endpoint_returns_database_notifications_before_history_items(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Agent One',
            'vici_user' => '1001',
        ]);
        $user->notify(new SupervisorUserNotification(
            message: 'Take your break after this call.',
            recipientType: 'USER',
            recipient: '1001',
            senderId: 99,
            showConfetti: false,
        ));

        CrmCallHistory::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'agent' => 'Agent One',
            'status' => 'RECORDED',
            'remarks' => 'Saved form',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread', 2)
            ->assertJsonPath('items.0.source', 'Supervisor')
            ->assertJsonPath('items.0.message', 'Take your break after this call.')
            ->assertJsonPath('items.0.read', false)
            ->assertJsonPath('items.1.source', 'Call & form history');
    }

    public function test_read_all_marks_database_notifications_and_history_items_as_read(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Agent One',
            'vici_user' => '1001',
        ]);
        $user->notify(new SupervisorUserNotification(
            message: 'Read me.',
            recipientType: 'USER',
            recipient: '1001',
            senderId: 99,
            showConfetti: false,
        ));
        CrmCallHistory::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'agent' => 'Agent One',
            'status' => 'RECORDED',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('items.0.read', true)
            ->assertJsonPath('items.1.read', true);
    }
}
