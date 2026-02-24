<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            return User::create([
                'username'  => $data['username'],
                'full_name' => $data['full_name'],
                'password'  => Hash::make($data['password']),
                'role'      => $data['role'],
                'vici_user' => $data['vici_user'] ?? null,
                'vici_pass' => $data['vici_pass'] ?? null,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->username  = $data['username'];
            $user->full_name = $data['full_name'];
            $user->role      = $data['role'];
            $user->vici_user = $data['vici_user'] ?? null;

            if (!empty($data['vici_pass'])) {
                $user->vici_pass = $data['vici_pass'];
            }
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();
            return $user;
        });
    }

    public function delete(User $user, User $requestingUser): bool
    {
        if ($user->id === $requestingUser->id) {
            return false;
        }
        $user->delete();
        return true;
    }
}
