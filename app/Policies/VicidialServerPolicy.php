<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VicidialServer;

class VicidialServerPolicy
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
        return $user->isSuperAdmin();
    }

    public function view(User $user, VicidialServer $server): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, VicidialServer $server): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, VicidialServer $server): bool
    {
        return $user->isSuperAdmin();
    }
}
