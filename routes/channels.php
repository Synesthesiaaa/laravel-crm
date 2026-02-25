<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Supervisor dashboard: real-time telephony stats and agent status.
 * Only users with supervisor or admin role may subscribe.
 */
Broadcast::channel('telephony.supervisor', function ($user) {
    return in_array($user->role ?? '', ['Super Admin', 'Admin', 'Team Leader'], true);
});
