<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Agent-specific telephony channel (alias for App.Models.User.{id}).
 */
Broadcast::channel('agent.{agent_id}', function ($user, $agentId) {
    return (int) $user->id === (int) $agentId;
});

/**
 * Supervisor dashboard: real-time telephony stats and agent status.
 */
Broadcast::channel('telephony.supervisor', function ($user) {
    return in_array($user->role ?? '', ['Super Admin', 'Admin', 'Team Leader'], true);
});
