<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LogSecurityEvent
{
    public function handleLogin(UserLoggedIn $event): void
    {
        Log::channel('security')->info('User logged in', [
            'user_id' => $event->userId,
            'ip' => $event->ipAddress,
        ]);

        $this->recordActivity(
            $event->userId,
            'login',
            'User logged in',
            ['ip_address' => $event->ipAddress],
        );
    }

    public function handleLogout(UserLoggedOut $event): void
    {
        Log::channel('security')->info('User logged out', [
            'user_id' => $event->userId,
        ]);

        $this->recordActivity(
            $event->userId,
            'logout',
            'User logged out',
            ['ip_address' => request()?->ip()],
        );
    }

    public function handle(UserLoggedIn|UserLoggedOut $event): void
    {
        if ($event instanceof UserLoggedIn) {
            $this->handleLogin($event);
        } else {
            $this->handleLogout($event);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordActivity(int $userId, string $event, string $description, array $properties): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }

        activity('security')
            ->causedBy($user)
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }
}
