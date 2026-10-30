<?php

namespace App\Services\Imports;

use Illuminate\Support\Facades\DB;

/** Shares the caller's transaction with user writes, diagnostics, and checkpoints. */
final class DatabaseUserImportIdentityStore implements UserImportIdentityStore
{
    public function __construct(private string $importId) {}

    public function contains(string $field, string $value): bool
    {
        return DB::table('user_import_identities')->where('import_id', $this->importId)
            ->where('identity', $this->key($field, $value))->exists();
    }

    public function remember(string $username, string $email): void
    {
        DB::table('user_import_identities')->insertOrIgnore([
            ['import_id' => $this->importId, 'identity' => $this->key('username', $username)],
            ['import_id' => $this->importId, 'identity' => $this->key('email', $email)],
        ]);
    }

    public function beginBatch(): void {}

    public function endBatch(): void {}

    public function close(): void {}

    private function key(string $field, string $value): string
    {
        return hash('sha256', $field.':'.mb_strtolower(trim($value), 'UTF-8'));
    }
}
