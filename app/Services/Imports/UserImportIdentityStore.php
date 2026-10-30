<?php

namespace App\Services\Imports;

interface UserImportIdentityStore
{
    public function contains(string $field, string $value): bool;

    public function remember(string $username, string $email): void;

    public function beginBatch(): void;

    public function endBatch(): void;

    public function close(): void;
}
