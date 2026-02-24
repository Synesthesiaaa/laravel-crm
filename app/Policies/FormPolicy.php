<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
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

    public function view(User $user, Form $form): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Form $form): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->isSuperAdmin();
    }
}
