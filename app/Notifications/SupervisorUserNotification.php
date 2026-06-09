<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SupervisorUserNotification extends Notification
{
    use Queueable;

    private readonly string $sentAt;

    public function __construct(
        public readonly string $message,
        public readonly string $recipientType,
        public readonly string $recipient,
        public readonly int $senderId,
        public readonly bool $showConfetti = false,
        ?string $sentAt = null,
    ) {
        $this->sentAt = $sentAt ?? now()->toIso8601String();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'source' => 'Supervisor',
            'title' => 'Supervisor notification',
            'message' => $this->message,
            'type' => 'info',
            'recipient_type' => $this->recipientType,
            'recipient' => $this->recipient,
            'sender_id' => $this->senderId,
            'sent_at' => $this->sentAt,
            'show_confetti' => $this->showConfetti,
        ];
    }
}
