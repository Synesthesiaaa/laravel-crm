<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use Illuminate\Support\Facades\Log;

class LogSecurityEvent
{
    public function handleLogin(UserLoggedIn $event): void
    {
        Log::channel('security')->info('User logged in', [
            'user_id' => $event->userId,
            'ip'      => $event->ipAddress,
        ]);
    }

    public function handleLogout(UserLoggedOut $event): void
    {
        Log::channel('security')->info('User logged out', [
            'user_id' => $event->userId,
        ]);
    }

    public function handle(UserLoggedIn|UserLoggedOut $event): void
    {
        if ($event instanceof UserLoggedIn) {
            $this->handleLogin($event);
        } else {
            $this->handleLogout($event);
        }
    }
}
