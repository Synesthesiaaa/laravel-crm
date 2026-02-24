<?php

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByUsername(string $username): ?User;

    public function validateCredentials(string $username, string $password): ?User;

    public function updateViciCredentials(int $userId, ?string $viciUser, ?string $viciPass): bool;
}
