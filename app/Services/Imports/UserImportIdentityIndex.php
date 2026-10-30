<?php

namespace App\Services\Imports;

use PDO;
use PDOStatement;
use RuntimeException;

/** Disk-backed identity hashes keep both import and preview memory bounded. */
final class UserImportIdentityIndex implements UserImportIdentityStore
{
    private ?PDO $database = null;

    private ?PDOStatement $lookup = null;

    private ?PDOStatement $insert = null;

    private string $path;

    public function __construct()
    {
        if (! extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The users importer requires the pdo_sqlite PHP extension.');
        }

        $path = tempnam(sys_get_temp_dir(), 'crm-user-identities-');
        if ($path === false) {
            throw new RuntimeException('Cannot create the temporary user identity index.');
        }
        $this->path = $path;

        try {
            $this->database = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->database->exec('PRAGMA cache_size = -2048');
            $this->database->exec('PRAGMA journal_mode = OFF');
            $this->database->exec('PRAGMA synchronous = OFF');
            $this->database->exec('CREATE TABLE identities (identity TEXT PRIMARY KEY) WITHOUT ROWID');
            $this->lookup = $this->database->prepare('SELECT 1 FROM identities WHERE identity = ?');
            $this->insert = $this->database->prepare('INSERT OR IGNORE INTO identities (identity) VALUES (?)');
        } catch (\Throwable $exception) {
            $this->close();

            throw $exception;
        }
    }

    public function contains(string $field, string $value): bool
    {
        $this->lookup->execute([$this->key($field, $value)]);
        $found = $this->lookup->fetchColumn() !== false;
        $this->lookup->closeCursor();

        return $found;
    }

    public function remember(string $username, string $email): void
    {
        $this->insert->execute([$this->key('username', $username)]);
        $this->insert->execute([$this->key('email', $email)]);
    }

    public function beginBatch(): void
    {
        $this->database->beginTransaction();
    }

    public function endBatch(): void
    {
        $this->database->commit();
    }

    public function close(): void
    {
        $this->lookup = null;
        $this->insert = null;
        $this->database = null;
        if (isset($this->path) && is_file($this->path)) {
            unlink($this->path);
        }
    }

    private function key(string $field, string $value): string
    {
        return hash('sha256', $field.':'.mb_strtolower(trim($value), 'UTF-8'));
    }
}
