<?php

namespace App\Policies;

use App\Models\LeadList;
use App\Models\User;

class LeadListPolicy
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

    public function view(User $user, LeadList $list): bool
    {
        return $user->isTeamLeader();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LeadList $list): bool
    {
        return $user->isAdmin();
    }

    public function toggle(User $user, LeadList $list): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LeadList $list): bool
    {
        return $user->isAdmin();
    }

    public function import(User $user, LeadList $list): bool
    {
        return $user->isAdmin();
    }

    public function export(User $user): bool
    {
        return $user->isTeamLeader();
    }
}
