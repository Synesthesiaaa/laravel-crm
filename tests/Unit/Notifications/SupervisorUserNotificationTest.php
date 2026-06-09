<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\SupervisorUserNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Tests\TestCase;

class SupervisorUserNotificationTest extends TestCase
{
    public function test_database_and_broadcast_payloads_match_frontend_shape(): void
    {
        $notification = new SupervisorUserNotification(
            message: 'Please switch queue.',
            recipientType: 'USER',
            recipient: '1001',
            senderId: 7,
            showConfetti: true,
        );
        $notifiable = new User(['id' => 42]);

        $this->assertSame(['database', 'broadcast'], $notification->via($notifiable));

        $databasePayload = $notification->toArray($notifiable);
        $this->assertSame('Supervisor', $databasePayload['source']);
        $this->assertSame('Supervisor notification', $databasePayload['title']);
        $this->assertSame('Please switch queue.', $databasePayload['message']);
        $this->assertSame('info', $databasePayload['type']);
        $this->assertSame('USER', $databasePayload['recipient_type']);
        $this->assertSame('1001', $databasePayload['recipient']);
        $this->assertSame(7, $databasePayload['sender_id']);
        $this->assertTrue($databasePayload['show_confetti']);
        $this->assertArrayHasKey('sent_at', $databasePayload);

        $broadcastPayload = $notification->toBroadcast($notifiable);
        $this->assertInstanceOf(BroadcastMessage::class, $broadcastPayload);
        $this->assertSame($databasePayload, $broadcastPayload->data);
    }
}
