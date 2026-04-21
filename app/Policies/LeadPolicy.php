<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isTeamLeader();
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isTeamLeader();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    public function dial(User $user, Lead $lead): bool
    {
        return $user->isAgent();
    }
}
