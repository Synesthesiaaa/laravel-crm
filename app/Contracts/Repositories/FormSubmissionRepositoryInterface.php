<?php

namespace App\Contracts\Repositories;

interface FormSubmissionRepositoryInterface
{
    /** @param array<string, mixed> $columnsAndValues */
    public function insert(string $tableName, array $columnsAndValues): int;
}
