<?php

namespace App\Services\Imports;

final readonly class UserImportBatch
{
    public function __construct(
        public int $offset,
        public int $record,
        public int $limit,
        public UserImportIdentityStore $identities,
    ) {}
}
