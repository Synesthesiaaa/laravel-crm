<?php

namespace App\Policies;

use App\Models\DispositionCode;
use App\Models\User;

class DispositionCodePolicy
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

    public function view(User $user, DispositionCode $code): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DispositionCode $code): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DispositionCode $code): bool
    {
        return $user->isAdmin();
    }
}
