<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DataMasterService
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * Returns allowed table names for a given campaign config.
     *
     * @param  array<string, mixed> $campaignConfig
     * @return list<string>
     */
    public function getAllowedTables(array $campaignConfig): array
    {
        $allowed = [];
        foreach ($campaignConfig['forms'] ?? [] as $formConfig) {
            $t = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
            if ($t !== '') {
                $allowed[] = $t;
            }
        }
        return $allowed;
    }

    public function getRecords(string $tableName, array $allowedTables, int $perPage = 20): LengthAwarePaginator
    {
        if (!$this->isTableAllowed($tableName, $allowedTables)) {
            return new LengthAwarePaginator([], 0, $perPage);
        }
        try {
            return DB::table($tableName)->orderByDesc('id')->paginate($perPage);
        } catch (\Throwable) {
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    public function getRecord(string $tableName, int $id, array $allowedTables): ?object
    {
        if (!$this->isTableAllowed($tableName, $allowedTables)) {
            return null;
        }
        return DB::table($tableName)->where('id', $id)->first();
    }

    /** @param array<string, mixed> $updates */
    public function updateRecord(string $tableName, int $id, array $updates, array $allowedTables): bool
    {
        if (!$this->isTableAllowed($tableName, $allowedTables)) {
            return false;
        }
        if (empty($updates)) {
            return true;
        }
        return DB::table($tableName)->where('id', $id)->update($updates) >= 0;
    }

    public function deleteRecord(string $tableName, int $id, array $allowedTables): bool
    {
        if (!$this->isTableAllowed($tableName, $allowedTables)) {
            return false;
        }
        return DB::table($tableName)->where('id', $id)->delete() > 0;
    }

    public function isTableAllowed(string $tableName, array $allowedTables): bool
    {
        return in_array($tableName, $allowedTables, true);
    }
}
