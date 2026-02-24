<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function validateCredentials(string $username, string $password): ?User
    {
        $user = $this->findByUsername($username);
        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }
        return $user;
    }

    public function updateViciCredentials(int $userId, ?string $viciUser, ?string $viciPass): bool
    {
        return User::where('id', $userId)->update([
            'vici_user' => $viciUser,
            'vici_pass' => $viciPass,
        ]) > 0;
    }
}
