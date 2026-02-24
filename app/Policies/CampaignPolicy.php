<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
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
        return $user->isAdmin();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }
}
