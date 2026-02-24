<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface FormFieldRepositoryInterface
{
    public function getFieldsForForm(string $campaignCode, string $formType): Collection;

    public function getCategorizedFields(string $campaignCode, string $formType): array;

    public function validateTableName(string $tableName, ?array $allowedTables = null): bool;
}
